CREATE TABLE IF NOT EXISTS sources (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_key VARCHAR(100) NOT NULL UNIQUE,
    source_name VARCHAR(255) NOT NULL,
    category VARCHAR(64) NOT NULL,
    code_path VARCHAR(255) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS fetch_jobs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_id BIGINT UNSIGNED NOT NULL,
    started_at TIMESTAMP NULL,
    finished_at TIMESTAMP NULL,
    status VARCHAR(32) NOT NULL,
    requested_by VARCHAR(128) NULL,
    throttle_profile VARCHAR(64) NULL,
    max_pages INT NULL,
    max_items INT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_fetch_jobs_source_status (source_id, status, started_at),
    CONSTRAINT fk_fetch_jobs_source FOREIGN KEY (source_id) REFERENCES sources(id)
);

CREATE TABLE IF NOT EXISTS fetch_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_id BIGINT UNSIGNED NOT NULL,
    source_id BIGINT UNSIGNED NOT NULL,
    endpoint_name VARCHAR(128) NOT NULL,
    request_params_json JSON NOT NULL,
    request_fingerprint CHAR(64) GENERATED ALWAYS AS (SHA2(JSON_EXTRACT(request_params_json, '$'), 256)) STORED,
    page_number INT NULL,
    cursor_value VARCHAR(255) NULL,
    retry_count INT NOT NULL DEFAULT 0,
    http_status INT NULL,
    response_time_ms INT NULL,
    throttled TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_fetch_requests_job_created (job_id, created_at),
    INDEX idx_fetch_requests_source_endpoint (source_id, endpoint_name, created_at),
    UNIQUE KEY uq_fetch_request_dedupe (job_id, source_id, endpoint_name, page_number, cursor_value, request_fingerprint),
    CONSTRAINT fk_fetch_requests_job FOREIGN KEY (job_id) REFERENCES fetch_jobs(id),
    CONSTRAINT fk_fetch_requests_source FOREIGN KEY (source_id) REFERENCES sources(id)
);

CREATE TABLE IF NOT EXISTS documents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_id BIGINT UNSIGNED NOT NULL,
    source_tool VARCHAR(128) NOT NULL,
    source_variant VARCHAR(128) NULL,
    fetch_transport VARCHAR(64) NULL,
    used_fallback TINYINT(1) NOT NULL DEFAULT 0,
    used_cache TINYINT(1) NOT NULL DEFAULT 0,
    semantic_related TINYINT(1) NOT NULL DEFAULT 0,
    full_download_completed TINYINT(1) NOT NULL DEFAULT 0,
    originating_endpoint VARCHAR(128) NULL,
    originating_request_params_json JSON NULL,
    originating_page_number INT NULL,
    originating_cursor_value VARCHAR(255) NULL,
    document_type VARCHAR(128) NULL,
    tertip VARCHAR(64) NULL,
    no VARCHAR(64) NULL,
    madde_no VARCHAR(64) NULL,
    title TEXT NULL,
    subtitle TEXT NULL,
    official_date DATE NULL,
    official_number VARCHAR(64) NULL,
    raw_identifier VARCHAR(255) NOT NULL,
    content_markdown LONGTEXT NULL,
    content_html LONGTEXT NULL,
    content_text LONGTEXT NULL,
    content_hash CHAR(64) NULL,
    language VARCHAR(16) DEFAULT 'tr',
    fetch_status VARCHAR(32) NOT NULL,
    first_seen_at TIMESTAMP NULL,
    last_seen_at TIMESTAMP NULL,
    last_job_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_documents_identity (source_id, document_type, tertip, no, madde_no, raw_identifier),
    INDEX idx_documents_hash (content_hash),
    INDEX idx_documents_last_job (last_job_id),
    CONSTRAINT fk_documents_source FOREIGN KEY (source_id) REFERENCES sources(id),
    CONSTRAINT fk_documents_last_job FOREIGN KEY (last_job_id) REFERENCES fetch_jobs(id)
);

CREATE TABLE IF NOT EXISTS document_metadata (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    document_id BIGINT UNSIGNED NOT NULL,
    metadata_json JSON NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_doc_meta_document (document_id),
    CONSTRAINT fk_doc_meta_document FOREIGN KEY (document_id) REFERENCES documents(id)
);

CREATE TABLE IF NOT EXISTS document_versions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    document_id BIGINT UNSIGNED NOT NULL,
    content_hash CHAR(64) NOT NULL,
    content_markdown LONGTEXT NULL,
    content_html LONGTEXT NULL,
    content_text LONGTEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    source_request_id BIGINT UNSIGNED NULL,
    UNIQUE KEY uq_document_version_hash (document_id, content_hash),
    INDEX idx_document_versions_request (source_request_id),
    CONSTRAINT fk_document_versions_document FOREIGN KEY (document_id) REFERENCES documents(id),
    CONSTRAINT fk_document_versions_request FOREIGN KEY (source_request_id) REFERENCES fetch_requests(id)
);

CREATE TABLE IF NOT EXISTS document_chunks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    document_id BIGINT UNSIGNED NOT NULL,
    chunk_index INT NOT NULL,
    chunk_text LONGTEXT NOT NULL,
    chunk_hash CHAR(64) NOT NULL,
    char_start INT NULL,
    char_end INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_document_chunk (document_id, chunk_index, chunk_hash),
    INDEX idx_document_chunk_hash (chunk_hash),
    CONSTRAINT fk_document_chunks_document FOREIGN KEY (document_id) REFERENCES documents(id)
);

CREATE TABLE IF NOT EXISTS rate_limit_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_id BIGINT UNSIGNED NOT NULL,
    request_id BIGINT UNSIGNED NULL,
    event_type VARCHAR(64) NOT NULL,
    wait_seconds INT NOT NULL,
    details JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_rate_limit_source_created (source_id, created_at),
    INDEX idx_rate_limit_request (request_id),
    CONSTRAINT fk_rate_limit_source FOREIGN KEY (source_id) REFERENCES sources(id),
    CONSTRAINT fk_rate_limit_request FOREIGN KEY (request_id) REFERENCES fetch_requests(id)
);
