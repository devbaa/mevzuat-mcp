# Mevzuat MCP Runtime Execution Flow (Step-by-Step)

This document describes how a user request is processed end-to-end through the MCP server.

## Entry Points

1. **MCP protocol over HTTP**: `app.py` exposes `mcp.http_app()` with MCP endpoint at `/mcp/`.
2. **CLI**: package script `mevzuat-mcp` runs `mevzuat_mcp_server:main`.
3. **Health check**: `/health` custom route for deployment monitoring.

---

## 1) Server Initialization Flow

1. Python imports `mevzuat_mcp_server.py`.
2. `FastMCP(...)` app is created with server instructions and capabilities text.
3. Primary client is initialized: `MevzuatApiClientNew(cache_ttl=3600, enable_cache=True)`.
4. Bedesten client is initialized: `BedestenClient(cache_ttl=3600, enable_cache=True)`.
5. Semantic search capability is checked using `is_openrouter_available()`.
6. If available, semantic components are initialized:
   - `OpenRouterEmbedder`
   - `MevzuatProcessor`
   - `EmbeddingCache(ttl=3600)`
7. All tool functions decorated with `@app.tool()` are registered.
8. In ASGI mode, `app.py` calls `mcp.http_app()` and exports it as `app`.

---

## 2) Generic MCP Request Handling Flow

1. **User** enters a prompt in an MCP-compatible client (Claude Desktop, 5ire, etc.).
2. **MCP client** decides tool call and sends tool name + JSON arguments to `/mcp/`.
3. **FastMCP server** resolves tool by name from registered `@app.tool()` handlers.
4. **Input handling layer** validates arguments using Python type hints + `pydantic.Field(...)` constraints.
5. If validation fails, MCP returns a structured tool error response.
6. If validation succeeds, async tool coroutine is executed.
7. Tool may call internal helpers, cache, and external APIs.
8. Tool returns either:
   - structured Pydantic model (e.g., `MevzuatSearchResultNew`), or
   - formatted string payload.
9. **FastMCP** serializes tool output into MCP response envelope.
10. **Client** receives structured output and renders it to the user/LLM.

---

## 3) Tool Inventory and Major Branches

## A. `mevzuat.gov.tr` tools (type-specific)

### Search tools
- `search_kanun`
- `search_teblig`
- `search_cbk`
- `search_cbyonetmelik`
- `search_cbbaskankarar`
- `search_cbgenelge`
- `search_khk`
- `search_tuzuk`
- `search_kurum_yonetmelik`

### Content retrieval tools
- `get_teblig_content`
- `get_cbbaskankarar_content`
- `get_cbgenelge_content`

### Search-within tools
- `search_within_kanun`
- `search_within_teblig`
- `search_within_cbk`
- `search_within_cbyonetmelik`
- `search_within_cbbaskankarar`
- `search_within_cbgenelge`
- `search_within_khk`
- `search_within_tuzuk`
- `search_within_kurum_yonetmelik`

## B. `bedesten.adalet.gov.tr` tools (unified)

- `search_mevzuat`
- `get_mevzuat_content`
- `search_within_mevzuat`
- `get_mevzuat_gerekce`
- `get_mevzuat_madde_tree`

---

## 4) `mevzuat.gov.tr` Search Flow (e.g., `search_kanun`)

1. Tool handler receives search params (`aranacak_ifade`, dates, paging, etc.).
2. Handler builds `MevzuatSearchRequestNew`.
3. Handler calls `mevzuat_client.search_documents(request)`.
4. Client ensures session by running Playwright (`_ensure_session`):
   1. Launch headless Chromium.
   2. Visit `https://www.mevzuat.gov.tr/`.
   3. Extract cookies + anti-forgery token.
5. Client builds DataTables payload (`draw`, `columns`, `start`, `length`, `parameters`).
6. Client sends POST to `/Anasayfa/MevzuatDatatable` with cookies/token.
7. On success, client maps each result row into `MevzuatDocumentNew`.
8. Client returns `MevzuatSearchResultNew`.
9. Tool returns model to FastMCP.
10. FastMCP returns structured response to MCP client.

### Fallback branch
- If session bootstrap fails (no token/cookies), client falls back to `search_documents_with_playwright`, where fetch POST is executed directly in browser page context.

---

## 5) `mevzuat.gov.tr` Content Retrieval Flow

1. Tool (`get_*_content` or `search_within_*`) calls `_get_content_with_tertip_fallback`.
2. Helper tries requested `mevzuat_tertip`; if empty/error, retries fallback tertip order.
3. `mevzuat_client.get_content(...)` executes with branch logic:

### Branch A: HTML-first path (most types)
1. Call `get_content_from_html(...)`.
2. Use Playwright to open iframe detail URL.
3. Extract HTML body and remove non-content tags.
4. Convert HTML to Markdown via `markitdown` (with BeautifulSoup fallback).
5. Return markdown content.

### Branch B: File download fallback
1. Build DOC/PDF URL by type.
2. Try DOC first (when available), convert stream to markdown.
3. If DOC fails, try PDF.
4. For CB Kararı / CB Genelgesi (`tur` 20/22):
   - ensure session cookies,
   - use Mistral OCR if API key exists,
   - fallback to `markitdown` PDF conversion if OCR fails.
5. Cache markdown and return.

---

## 6) `search_within_*` Flow (Keyword vs Semantic)

1. Tool receives identifiers + `keyword` + options.
2. Tool retrieves markdown content (flow in section 5).
3. Tool chooses branch:

### Keyword branch (`semantic=False`)
1. Split into articles (or chunks for non-article structures).
2. Evaluate query using `_matches_query`:
   - exact phrase with quotes,
   - `AND`, `OR`, `NOT` operators.
3. Score matches by occurrence counts.
4. Sort descending by relevance score.
5. Format result text with matching MADDE/chunk contents.

### Semantic branch (`semantic=True`)
1. Verify semantic availability (`OPENROUTER_API_KEY`).
2. Check embedding cache by `(mevzuat_tur, tertip, no, content_hash)`.
3. If cache miss:
   1. Process legislation into article/chunk documents.
   2. Call OpenRouter embeddings API for document vectors.
   3. Build in-memory `VectorStore`.
   4. Save in embedding cache.
4. Embed query via OpenRouter.
5. Run cosine-similarity search (`top_k`, `threshold`).
6. Format semantic matches with similarity scores.

---

## 7) `bedesten` Unified Search Flow (`search_mevzuat`)

1. Tool receives unified filters (`phrase`, `mevzuat_adi`, `mevzuat_no`, type/date filters).
2. Tool normalizes and validates `mevzuat_tur` list.
3. If user is browsing (no query fields), tool sets all valid types.
4. Tool calls `bedesten_client.search_documents(...)`.
5. Client builds wrapped payload: `{data: {...}, applicationName: "UyapMevzuat", paging: true}`.
6. Client converts date filters to API-required ISO UTC boundaries.
7. Client POSTs `/searchDocuments`.
8. Client checks response metadata `FMTY`.
9. On success, maps `mevzuatList` items to `BedMevzuatDocument` models.
10. Tool formats readable list lines with `mevzuatId` and optional `gerekceId`.
11. FastMCP returns string result.

---

## 8) `bedesten` Detail Flows

## A. `get_mevzuat_content`
1. Tool calls `bedesten_client.get_document_plain_text(mevzuat_id)`.
2. Client fetches `/getDocumentContent` (`documentType=MEVZUAT`).
3. Base64 content is decoded.
4. HTML tags are stripped to plain text.
5. Plain text is cached and returned.

## B. `search_within_mevzuat`
1. Tool gets plain text via `get_document_plain_text`.
2. Splits plain text into MADDE blocks.
3. Applies keyword boolean matching/scoring.
4. Formats and returns matching articles.

## C. `get_mevzuat_gerekce`
1. Tool calls `get_gerekce_content(gerekce_id)`.
2. Client POSTs `/getGerekceContent`.
3. Decodes base64, strips HTML, returns rationale text.

## D. `get_mevzuat_madde_tree`
1. Tool calls `get_article_tree(mevzuat_id)`.
2. Client POSTs `/mevzuatMaddeTree`.
3. Parses nested nodes into `BedMaddeNode`.
4. Tool flattens/counts + pretty-prints hierarchy.
5. Returns formatted tree text.

---

## 9) Caching and Performance-Critical Points

1. **Mevzuat markdown cache**: avoids repeated HTML/PDF conversions.
2. **Bedesten cache**: avoids repeated document/tree/gerekçe calls.
3. **Embedding cache**: avoids recomputing semantic vectors for unchanged content.
4. **Latency hotspots**:
   - Playwright session/bootstrap,
   - OCR/markitdown conversion for large PDFs,
   - OpenRouter embedding calls,
   - large content serialization in response payloads.

---

## 10) Failure/Recovery Paths

1. External API non-success metadata (`FMTY != SUCCESS`) returns tool-level error message.
2. Network/HTTP exceptions are caught and converted to string/model error outputs.
3. Search session failure in mevzuat client triggers Playwright-fetch fallback path.
4. OCR failure triggers PDF conversion fallback.
5. No-result cases return explicit human-readable "No results found" responses.
