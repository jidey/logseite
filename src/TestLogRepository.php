<?php
/**
 * Repository class to access the test logs
 * FINAL VERSION: Full support for the per-product mapping
 *
 * Handles all cases (X15, X16, X17) with products (gWWebSel, weWebSel, gWClient)
 */

class TestLogRepository {

    private $pdo;
    private $product_table_map;
    private $cache;  // New attribute for the cache

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;

        // Initialize the cache
        require_once __DIR__ . '/Cache.php';
        $this->cache = new Cache();

        // Get the mapping from config.php
        // If not available, use a default mapping
        if (isset($GLOBALS['product_table_map'])) {
            $this->product_table_map = $GLOBALS['product_table_map'];
        } else {
            // Default mapping (if config.php was not loaded)
            $this->product_table_map = [
                'rc_x18' => ['gWWebSel' => 'x18_rc', 'weWebSel' => 'we_rc', 'gWClient' => 'x18_gwrc'],
                'hf_x18' => ['gWWebSel' => 'x18_hf', 'weWebSel' => 'we_hf', 'gWClient' => 'x18_gwhf'],
                'dev_x18' => ['gWWebSel' => 'x18_dev', 'weWebSel' => 'we_dev', 'gWClient' => 'x18_gwdev'],
                'rc_x17' => ['gWWebSel' => 'x17_rc', 'weWebSel' => 'we_rc', 'gWClient' => 'x17_gwrc'],
                'hf_x17' => ['gWWebSel' => 'x17_hf', 'weWebSel' => 'we_hf', 'gWClient' => 'x17_gwhf'],
                'dev_x17' => ['gWWebSel' => 'x17_dev', 'weWebSel' => 'we_dev', 'gWClient' => 'x17_gwdev'],
                //'rc_x16' => ['gWWebSel' => 'x16_rc', 'weWebSel' => 'we_rc', 'gWClient' => 'x16_gwrc'],
                'hf_x16' => ['gWWebSel' => 'x16_hf', 'weWebSel' => 'we_hf', 'gWClient' => 'x16_gwhf'],
                //'dev_x16' => ['gWWebSel' => 'x16_dev', 'weWebSel' => 'we_dev', 'gWClient' => 'x16_gwdev'],
                //'rc_x15' => ['gWWebSel' => 'x15_rc',  'weWebSel' => 'we_rc', 'gWClient' => 'x15_gwrc'],
                //'hf_x15' => ['gWWebSel' => 'x15_hf', 'weWebSel' => 'we_hf', 'gWClient' => 'x15_gwhf'],
                //'dev_x15' => ['gWWebSel' => 'x15_dev', 'weWebSel' => 'we_dev', 'gWClient' => 'x15_gwdev'],
            ];
        }
    }
    
    /**
     * Gets the table name for a test type and product
     *
     * @param string $testType (e.g. 'rc_x17', 'hf_x16', 'dev_x15')
     * @param string|null $product (e.g. 'gWWebSel', 'weWebSel', 'gWClient')
     * @return string
     * @throws Exception
     */
    public function getTableForTestType(string $testType, ?string $product = null): string {
        // Feature branches: the testType IS the table name
		if ($testType === 'we_feat' || $testType === 'web_feat') {
			return $testType;
		}
		// Check that the testType exists
        if (!isset($this->product_table_map[$testType])) {
            throw new Exception("Unknown test type: " . htmlspecialchars($testType));
        }

        $testTypeMap = $this->product_table_map[$testType];

        // If a product is specified, check it
        if ($product) {
            if (!isset($testTypeMap[$product])) {
                throw new Exception("Unknown product for test type: " . htmlspecialchars($product));
            }
            $tableName = $testTypeMap[$product];
        } else {
            // If no product, take the first one available
            // (or throw an exception if a product is required)
            $tableName = reset($testTypeMap); // Takes the first table
        }

        // Additional security validation
        if (!preg_match('/^[a-z0-9_]+$/', $tableName)) {
            throw new Exception("Invalid table name format");
        }
        
        return $tableName;
    }
    
    /**
     * Gets the available jobs for a version and product
     *
     * @param string $testType
     * @param string|null $product
     * @return array
     */
    public function getJobsByTestType(
        string $testType,
        ?string $product = null
    ): array {
        try {
            $tableName = $this->getTableForTestType($testType, $product);

            // For weWebSel, the Testtype in the DB is the table name (we_rc, we_hf, we_dev)
            // For the others, it's the testType (rc_x17, hf_x17, dev_x17, etc)
            $dbTestType = $testType;
            if ($product == "weWebSel") {
                $dbTestType = $tableName;
            }
            
            $query = "SELECT DISTINCT JJob FROM `$tableName` 
                      WHERE TestLogTyp = 'Main' 
                      AND JJob != ''
                      AND Testtype = :testtype";
            
            $params = [':testtype' => $dbTestType];
            
            if ($product) {
                $query .= " AND Product = :product";
                $params[':product'] = $product;
            }
            
            $query .= " ORDER BY JJob ASC";
            
            return $this->executeQuery($query, $params);
        } catch (Exception $e) {
            error_log("getJobsByTestType error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Gets the latest test runs for a job
     *
     * @param string $testType
     * @param string $jobName
     * @param string|null $product
     * @param string|null $browser
     * @param int $limit
     * @return array
     */
    public function getLatestRunsForJob(
        string $testType,
        string $jobName,
        ?string $product = null,
        ?string $browser = null,
        int $limit = 50
    ): array {
        try {
            $tableName = $this->getTableForTestType($testType, $product);

            // For weWebSel, the Testtype in the DB is the table name
            $dbTestType = $testType;
            if ($product == "weWebSel") {
                $dbTestType = $tableName;
            }

            // This query retrieves the latest run for each unique parameter
            $query = "SELECT l1.* FROM `$tableName` l1 
                      LEFT JOIN `$tableName` l2 
                      ON (l1.JParam = l2.JParam 
                          AND l1.JJob = l2.JJob 
                          AND l1.TestLogTyp = l2.TestLogTyp 
                          AND l1.Testtype = l2.Testtype 
                          AND l1.AutoID < l2.AutoID)
                      WHERE l1.JJob = :jjob
                      AND l1.Testtype = :testtype
                      AND l1.TestLogTyp = 'Main'
                      AND l2.AutoID IS NULL";
            
            $params = [
                ':jjob' => $jobName,
                ':testtype' => $dbTestType,
            ];
            
            if ($product) {
                $query .= " AND l1.Product = :product";
                $params[':product'] = $product;
            }
            
            if ($browser) {
                $query .= " AND l1.Browser = :browser";
                $params[':browser'] = $browser;
            }
            
            $query .= " ORDER BY l1.RunDate DESC LIMIT :limit";
            
            $stmt = $this->pdo->prepare($query);
            
            // Bind the regular parameters
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }

            // Bind the limit as an integer
            $stmt->bindValue(':limit', min($limit, 500), PDO::PARAM_INT);
            
            $stmt->execute();
            return $stmt->fetchAll();
            
        } catch (Exception $e) {
            error_log("getLatestRunsForJob error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Gets the details of a specific run
     *
     * @param string $testType
     * @param int $autoId
     * @param string|null $product
     * @return array|null
     */
    public function getRunDetails(string $testType, int $autoId, ?string $product = null): ?array {
        try {
            $tableName = $this->getTableForTestType($testType, $product);
            
            $query = "SELECT * FROM `$tableName` WHERE AutoID = :autoid LIMIT 1";
            
            $results = $this->executeQuery($query, [':autoid' => $autoId]);
            
            return !empty($results) ? $results[0] : null;
        } catch (Exception $e) {
            error_log("getRunDetails error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Marks a run as checked
     *
     * @param string $testType
     * @param int $autoId
     * @param bool $checked
     * @param string|null $product
     * @return bool
     */
    public function markAsChecked(
        string $testType,
        int $autoId,
        bool $checked = true,
        ?string $product = null
    ): bool {
        try {
            $tableName = $this->getTableForTestType($testType, $product);

            // If checked (Validated), change the result to Flaky
            // TearDownFailed = 0, TearDownWarning = 1 (Flaky)
            if ($checked) {
                $query = "UPDATE `$tableName` SET checked = :checked, TearDownFailed = 0, TearDownWarning = 1 WHERE AutoID = :autoid";
            } else {
                // If unchecked, set the result back to Failed
                // TearDownFailed = 1, TearDownWarning = 0
                $query = "UPDATE `$tableName` SET checked = :checked, TearDownFailed = 1, TearDownWarning = 0 WHERE AutoID = :autoid";
            }
            
            $stmt = $this->pdo->prepare($query);
            $stmt->bindValue(':checked', $checked ? 1 : 0, PDO::PARAM_INT);
            $stmt->bindValue(':autoid', $autoId, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("markAsChecked error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Gets the statistics for a version
     *
     * @param string $testType
     * @param string|null $product
     * @return array
     */
    public function getStatistics(string $testType, ?string $product = null): array {
        try {
            $tableName = $this->getTableForTestType($testType, $product);

            // For weWebSel, the Testtype in the DB is the table name
            $dbTestType = $testType;
            if ($product == "weWebSel") {
                $dbTestType = $tableName;
            }
            
            $query = "SELECT 
                        COUNT(DISTINCT JJob) as total_jobs,
                        COUNT(DISTINCT Product) as total_products,
                        COUNT(*) as total_runs,
                        SUM(CASE WHEN TearDownFailed = 0 AND running != 2 THEN 1 ELSE 0 END) as passed_runs,
                        SUM(CASE WHEN TearDownFailed > 0 THEN 1 ELSE 0 END) as failed_runs,
                        SUM(CASE WHEN running = 2 THEN 1 ELSE 0 END) as running_runs,
                        MAX(RunDate) as last_run_date
                      FROM `$tableName`
                      WHERE TestLogTyp = 'Main'
                      AND Testtype = :testtype";
            
            if ($product) {
                $query .= " AND Product = :product";
                $results = $this->executeQuery($query, [':testtype' => $dbTestType, ':product' => $product]);
            } else {
                $results = $this->executeQuery($query, [':testtype' => $dbTestType]);
            }
            
            return !empty($results) ? $results[0] : [];
        } catch (Exception $e) {
            error_log("getStatistics error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Finds unverified failed runs
     *
     * @param string $testType
     * @param int $limit
     * @param string|null $product
     * @return array
     */
    public function getUnverifiedFailedRuns(
        string $testType,
        int $limit = 100,
        ?string $product = null
    ): array {
        try {
            $tableName = $this->getTableForTestType($testType, $product);

            // For weWebSel, the Testtype in the DB is the table name
            $dbTestType = $testType;
            if ($product == "weWebSel") {
                $dbTestType = $tableName;
            }
            
            $query = "SELECT * FROM `$tableName`
                      WHERE TestLogTyp = 'Main'
                      AND Testtype = :testtype
                      AND checked = 0
                      AND (TearDownFailed > 0 OR running = 2)";
            
            if ($product) {
                $query .= " AND Product = :product";
            }
            
            $query .= " ORDER BY RunDate DESC LIMIT :limit";
            
            $stmt = $this->pdo->prepare($query);
            $stmt->bindValue(':testtype', $dbTestType, PDO::PARAM_STR);
            
            if ($product) {
                $stmt->bindValue(':product', $product, PDO::PARAM_STR);
            }
            
            $stmt->bindValue(':limit', min($limit, 500), PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("getUnverifiedFailedRuns error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Executes a prepared SELECT query
     * Internal convenience method
     *
     * @param string $query
     * @param array $params
     * @return array
     */
    private function executeQuery(string $query, array $params = []): array {
        try {
            $stmt = $this->pdo->prepare($query);
            
            foreach ($params as $key => $value) {
                $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
                $stmt->bindValue($key, $value, $type);
            }
            
            $stmt->execute();
            return $stmt->fetchAll();
            
        } catch (Exception $e) {
            error_log("executeQuery error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Checks that the table exists
     *
     * @param string $tableName
     * @return bool
     */
    public function tableExists(string $tableName): bool {
        try {
            $result = $this->pdo->query("SHOW TABLES LIKE '$tableName'");
            return $result->rowCount() > 0;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Gets the available versions
     *
     * @return array
     */
    public function getAvailableVersions(): array {
        return array_keys($this->product_table_map);
    }

    /**
     * Gets the available products for a version
     *
     * @param string $testType
     * @return array
     */
    public function getProductsForVersion(string $testType): array {
        if (isset($this->product_table_map[$testType])) {
            return array_keys($this->product_table_map[$testType]);
        }
        return [];
    }
    
    /**
     * Gets the full mapping (for debugging)
     *
     * @return array
     */
    public function getMapping(): array {
        return $this->product_table_map;
    }

    /**
     * Gets the available TestTypes for a product (based on the actual data)
     *
     * @param string $product
     * @return array
     */
    public function getAvailableTestTypesForProduct(string $product): array {
        // Check the cache first
        $cacheKey = "testTypes_{$product}";
        $cached = $this->cache->get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $availableTestTypes = [];

        // For weWebSel, return only the X18, X17 versions
        if ($product === "weWebSel") {
            $availableTestTypes = ['dev_x18', 'rc_x18', 'hf_x18', 'dev_x17', 'rc_x17', 'hf_x17'];
            // Cache for 1 hour (3600 seconds)
            $this->cache->set($cacheKey, $availableTestTypes, 3600);
            return $availableTestTypes;
        }

        // For each version, check whether the product exists in the mapping.
        // Since centralization (config/versions_config.php), a branch marked
        // 'active'/'future' for this product is displayed directly, without
        // a counting query: no need to wait for data to exist in the database
        // for a newly added version to appear in the selector (see
        // config/versions_config.php for each branch's status:
        // 'active' | 'future' | 'retired').
        foreach ($this->product_table_map as $testType => $products) {
            if (isset($products[$product])) {
                $availableTestTypes[] = $testType;
            }
        }

        // Cache the result for 1 hour (3600 seconds)
        $this->cache->set($cacheKey, $availableTestTypes, 3600);
        
        return $availableTestTypes;
    }
    
    /**
     * Gets the notes/tags for a TestSet
     *
     * @param string $testType
     * @param string $jJob
     * @param string $jParam
     * @param string|null $product
     * @return string|null
     */
    public function getTestSetNotes(string $testType, string $jJob, string $jParam, ?string $product = null): ?string {
        try {
            $tableName = $this->getTableForTestType($testType, $product);
            $tagsTableName = $tableName . '_tags';

            // Check that the _tags table exists
            if (!$this->tableExists($tagsTableName)) {
                return null;
            }
            
            $query = "SELECT testnotiz FROM `$tagsTableName` 
                      WHERE JJob = :jjob 
                      AND JParam = :jparam
                      LIMIT 1";
            
            $stmt = $this->pdo->prepare($query);
            $stmt->bindValue(':jjob', $jJob, PDO::PARAM_STR);
            $stmt->bindValue(':jparam', $jParam, PDO::PARAM_STR);
            $stmt->execute();
            
            $result = $stmt->fetch();
            
            return !empty($result) && !empty($result['testnotiz']) ? $result['testnotiz'] : null;
        } catch (Exception $e) {
            error_log("getTestSetNotes error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Clears the cache (useful after updates)
     * @return int Number of files deleted
     */
    public function clearCache() {
        return $this->cache->flush();
    }

    /**
     * Cleans up expired cache files
     * @return int Number of files deleted
     */
    public function cleanupCache() {
        return $this->cache->cleanup();
    }

    /**
     * Gets the cache stats (for debugging)
     * @return array
     */
    public function getCacheStats() {
        return $this->cache->getStats();
    }
}
?>
