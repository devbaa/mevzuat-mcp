# Semantic Search Subsystem Report

## Scope

This report analyzes the `semantic_search/` folder implementation and documents:

- Component responsibilities
- End-to-end runtime execution flow
- Data contracts and cache behavior
- Failure modes and operational risks
- Performance characteristics and tuning points

---

## 1) Module Inventory

## 1.1 `semantic_search/embedder.py`

**Purpose:** External embedding provider integration (OpenRouter) and embedding vector preparation.

### Key responsibilities
1. Detect semantic feature availability from environment (`OPENROUTER_API_KEY`).
2. Resolve embedding model from env (`EMBEDDING_MODEL`) with fallback.
3. Initialize OpenAI-compatible client using OpenRouter base URL.
4. Format query/document text based on model family (`e5` vs non-`e5`).
5. Call embeddings API for single query or document batches.
6. L2-normalize vectors for cosine similarity.

### Public API
- `is_openrouter_available() -> bool`
- `get_embedding_model() -> str`
- `OpenRouterEmbedder.encode_query(query) -> np.ndarray`
- `OpenRouterEmbedder.encode_documents(documents, titles, batch_size) -> np.ndarray`

---

## 1.2 `semantic_search/processor.py`

**Purpose:** Transform raw legislation text into searchable semantic units.

### Key responsibilities
1. Decide splitting strategy by legislation type:
   - article-based (`ARTICLE_BASED_TYPES`)
   - chunk-based (`CHUNK_BASED_TYPES`)
   - Tebliğ mixed strategy (articles first, chunk fallback)
2. Build `DocumentChunk` objects with deterministic metadata.
3. Create overlapping chunk windows for non-article structures.
4. Preserve metadata required for output formatting (`madde_no`, `chunk_index`, etc.).

### Public API
- `MevzuatProcessor.process_legislation(markdown_content, mevzuat_no, mevzuat_tur) -> List[DocumentChunk]`

---

## 1.3 `semantic_search/vector_store.py`

**Purpose:** In-memory vector index and top-k similarity retrieval.

### Key responsibilities
1. Store `Document` entries with text + embedding + metadata.
2. Build dense matrix index (`np.vstack`) on insert.
3. Execute cosine similarity via dot product (with normalized vectors).
4. Apply optional threshold filtering.
5. Return sorted `(Document, score)` pairs.

### Public API
- `VectorStore.add_documents(ids, texts, embeddings, metadata) -> int`
- `VectorStore.search(query_embedding, top_k, threshold) -> List[(Document, float)]`
- `VectorStore.clear()` / `VectorStore.size()`

---

## 1.4 `semantic_search/cache.py`

**Purpose:** Reuse expensive semantic artifacts (vector index + chunks).

### Key responsibilities
1. Cache per legislation key: `emb:{tur}.{tertip}.{no}`.
2. Validate TTL expiration.
3. Validate content hash continuity (invalidates cache if text changed).
4. Return cached `(vector_store, chunks)` if still valid.

### Public API
- `EmbeddingCache.get(...) -> Optional[(vector_store, chunks)]`
- `EmbeddingCache.put(...)`
- `EmbeddingCache.clear()` / `EmbeddingCache.size()`

---

## 2) End-to-End Semantic Search Runtime Flow

This flow is triggered from `search_within_*` tools when `semantic=True`.

1. Tool handler checks semantic availability (`SEMANTIC_SEARCH_AVAILABLE`).
2. Tool calls shared helper `_semantic_search_within(...)`.
3. Helper fetches legislation content (with tertip fallback).
4. Helper validates non-empty markdown content.
5. Helper attempts cache hit in `EmbeddingCache` using `(tur, tertip, no, content_hash)`.

### Cache-hit branch
6. Reuse cached `VectorStore` and chunk metadata.
7. Encode user query using `OpenRouterEmbedder.encode_query(...)`.
8. Search vector store with `top_k` and `threshold`.
9. Format and return semantic result blocks.

### Cache-miss branch
6. Process content into `DocumentChunk` list via `MevzuatProcessor`.
7. Build document text/title arrays.
8. Generate document embeddings in batches via OpenRouter API.
9. Initialize `VectorStore(dimension=embedder.dimension)`.
10. Add documents and embeddings to index.
11. Put `(vector_store, chunks)` into `EmbeddingCache`.
12. Encode query and run similarity search.
13. Format and return semantic result blocks.

---

## 3) Data Model and Contract Summary

## 3.1 `DocumentChunk` fields
- `chunk_id`: stable id composed from legislation id + article/chunk info
- `text`: searchable text body
- `title`: semantic title context
- `metadata`:
  - common: `mevzuat_no`, `mevzuat_tur`
  - article mode: `madde_no`, `madde_title`, `type='article'`
  - chunk mode: `chunk_index`, `total_chunks`, `type='chunk'`

## 3.2 Embedding contract
- Model dimensions are fixed via `EMBEDDING_MODELS` map.
- Query and documents are normalized (L2).
- Similarity in `VectorStore.search` is dot product of normalized vectors (cosine-equivalent).

## 3.3 Cache validity contract
A cache entry is valid only if:
1. Key exists.
2. Not expired (`time.time() <= expires_at`).
3. Content hash matches current content text.

---

## 4) Performance Analysis

## 4.1 Cost centers
1. **Embedding generation** (external API latency + tokenized payload size).
2. **Document splitting volume** (many articles/chunks increase embedding count).
3. **Matrix memory footprint** (`N x D` float32 embeddings).
4. **Cold starts** where cache is empty.

## 4.2 Existing optimization mechanisms
1. Batch embedding (`batch_size=50`) to reduce request overhead.
2. Reuse vector index via `EmbeddingCache` with TTL.
3. Content-hash invalidation to avoid stale semantic indices.
4. Threshold filtering in vector search to trim weak matches.

## 4.3 Potential bottlenecks
1. Large laws with high article counts can produce large in-memory indices.
2. Concurrent requests for same uncached document may duplicate embedding work.
3. In-memory cache is process-local (no sharing across workers/pods).
4. No explicit rate-limit/retry/backoff strategy in embedder methods.

---

## 5) Failure Modes and Current Behavior

## 5.1 Configuration failures
- Missing `OPENROUTER_API_KEY`: semantic mode unavailable.
- Unknown `EMBEDDING_MODEL`: warning + fallback to default model.

## 5.2 Dependency/API failures
- OpenRouter request exceptions bubble up from embedder encode methods.
- Upstream tool catches and returns user-facing error string.

## 5.3 Data-quality failures
- Empty or too-short content (`< min_chunk_size`) yields no chunks.
- Vector search with no results above threshold returns empty list.

## 5.4 Cache failures
- TTL expiration or hash mismatch invalidates entry and forces recompute.

---

## 6) Operational Recommendations

## 6.1 Reliability
1. Add retry + exponential backoff for embedding calls.
2. Add timeout/error typing to distinguish transient vs permanent errors.
3. Guard against duplicate cold-start embedding jobs for same key (single-flight lock).

## 6.2 Observability
1. Add metrics:
   - embedding request latency
   - cache hit/miss rate
   - chunks per document
   - vector index size
2. Add structured logs including legislation key and model name.

## 6.3 Scalability
1. Consider external/shared cache for multi-worker deployments.
2. Consider persistent vector storage if semantic traffic grows.
3. Add max-document-size controls to prevent unbounded memory growth.

## 6.4 Relevance quality
1. Tune chunk size/overlap by type-specific evaluation.
2. Consider hybrid retrieval (keyword pre-filter + semantic rerank).
3. Calibrate threshold defaults per mevzuat type.

---

## 7) Quick Sequence Diagram (Text)

1. User calls `search_within_*` with `semantic=True`.
2. Tool checks semantic feature availability.
3. Tool obtains legislation markdown.
4. Semantic helper checks embedding cache.
5. If miss: process -> embed docs -> build vector index -> cache.
6. Embed query.
7. Run vector search (`top_k`, `threshold`).
8. Format article/chunk-centric output with similarity scores.
9. Return text response through MCP.

---

## 8) Conclusion

The `semantic_search/` subsystem is cleanly modularized (embedder, processor, vector index, cache) and already includes practical baseline optimizations (batching, TTL cache, content-hash validation, normalized cosine retrieval). The primary risks are external API reliability, concurrent cold-start duplication, and process-local cache limitations under horizontal scaling. Addressing these with retry/locking/metrics/shared cache would materially improve production robustness.
