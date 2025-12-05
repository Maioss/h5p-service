USE h5p_service;

-- Table for H5P libraries
CREATE TABLE IF NOT EXISTS h5p_libraries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    title VARCHAR(255) NOT NULL,
    major_version INT NOT NULL,
    minor_version INT NOT NULL,
    patch_version INT NOT NULL,
    runnable INT NOT NULL DEFAULT 1,
    restricted INT NOT NULL DEFAULT 0,
    fullscreen INT NOT NULL DEFAULT 0,
    embed_types VARCHAR(255) NOT NULL DEFAULT '',
    preloaded_js TEXT,
    preloaded_css TEXT,
    drop_library_css TEXT,
    semantics TEXT,
    has_icon INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY (
        name,
        major_version,
        minor_version,
        patch_version
    )
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Table for library dependencies
CREATE TABLE IF NOT EXISTS h5p_library_dependencies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    library_id INT NOT NULL,
    required_library_id INT NOT NULL,
    dependency_type VARCHAR(31) NOT NULL,
    FOREIGN KEY (library_id) REFERENCES h5p_libraries (id) ON DELETE CASCADE,
    FOREIGN KEY (required_library_id) REFERENCES h5p_libraries (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Table for H5P content
CREATE TABLE IF NOT EXISTS h5p_contents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    library_id INT NOT NULL,
    parameters LONGTEXT NOT NULL,
    filtered TEXT,
    slug VARCHAR(127) NOT NULL,
    embed_type VARCHAR(127) NOT NULL DEFAULT 'div',
    disable INT NOT NULL DEFAULT 0,
    content_type VARCHAR(127),
    author VARCHAR(127),
    license VARCHAR(7),
    keywords TEXT,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (library_id) REFERENCES h5p_libraries (id) ON DELETE CASCADE,
    UNIQUE KEY (slug)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Table for content library dependencies
CREATE TABLE IF NOT EXISTS h5p_content_libraries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content_id INT NOT NULL,
    library_id INT NOT NULL,
    dependency_type VARCHAR(31) NOT NULL,
    weight INT NOT NULL DEFAULT 0,
    drop_css INT NOT NULL DEFAULT 0,
    FOREIGN KEY (content_id) REFERENCES h5p_contents (id) ON DELETE CASCADE,
    FOREIGN KEY (library_id) REFERENCES h5p_libraries (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Table for H5P Hub cache
CREATE TABLE IF NOT EXISTS h5p_libraries_hub_cache (
    id INT AUTO_INCREMENT PRIMARY KEY,
    machine_name VARCHAR(255) NOT NULL,
    major_version INT NOT NULL,
    minor_version INT NOT NULL,
    patch_version INT NOT NULL,
    h5p_major_version INT,
    h5p_minor_version INT,
    title VARCHAR(255) NOT NULL,
    summary TEXT,
    description TEXT,
    icon VARCHAR(511),
    created_at BIGINT NOT NULL,
    updated_at BIGINT NOT NULL,
    is_recommended INT NOT NULL DEFAULT 0,
    popularity INT NOT NULL DEFAULT 0,
    screenshots TEXT,
    license TEXT,
    example VARCHAR(511),
    tutorial VARCHAR(511),
    keywords TEXT,
    categories TEXT,
    owner VARCHAR(511),
    UNIQUE KEY (
        machine_name,
        major_version,
        minor_version,
        patch_version
    )
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Table for counters
CREATE TABLE IF NOT EXISTS h5p_counters (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(63) NOT NULL,
    library_name VARCHAR(127) NOT NULL,
    library_version VARCHAR(31) NOT NULL,
    num INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Table for events/logs
CREATE TABLE IF NOT EXISTS h5p_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    type VARCHAR(63) NOT NULL,
    sub_type VARCHAR(63) NOT NULL,
    content_id INT NOT NULL,
    content_title VARCHAR(255) NOT NULL,
    library_name VARCHAR(127) NOT NULL,
    library_version_VARCHAR (31) NOT NULL
) ENGINE = Inn oDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Table for options/settings (UUID, cache timestamps, etc.)
CREATE TABLE IF NOT EXISTS h5p_options (
    id INT AUTO_INCREMENT PRIMARY KEY,
    option_name VARCHAR(255) NOT NULL UNIQUE,
    option_value LONGTEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Insert a test record to verify database is working
INSERT IGNORE INTO
    h5p_counters (
        type,
        library_name,
        library_version,
        num
    )
VALUES (
        'database-init',
        'test',
        '1.0.0',
        1
    );