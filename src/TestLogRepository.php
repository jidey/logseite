<?php
/**
 * Classe Repository pour accéder aux logs de test
 * VERSION FINALE : Support complet du mapping par produit
 * 
 * Gère tous les cas (X15, X16, X17) avec produits (gWWebSel, weWebSel, gWClient)
 */

class TestLogRepository {
    
    private $pdo;
    private $product_table_map;
    private $cache;  // Nouvel attribut pour le cache
    
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        
        // Initialiser le cache
        require_once __DIR__ . '/Cache.php';
        $this->cache = new Cache();
        
        // Récupérer le mapping depuis config.php
        // Si not disponible, utiliser un mapping par défaut
        if (isset($GLOBALS['product_table_map'])) {
            $this->product_table_map = $GLOBALS['product_table_map'];
        } else {
            // Mapping par défaut (si config.php n'a pas été chargé)
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
     * Récupère le nom de table pour un type de test et produit
     * 
     * @param string $testType (ex: 'rc_x17', 'hf_x16', 'dev_x15')
     * @param string|null $product (ex: 'gWWebSel', 'weWebSel', 'gWClient')
     * @return string
     * @throws Exception
     */
    public function getTableForTestType(string $testType, ?string $product = null): string {
        // Branches feature : le testType EST le nom de la table
		if ($testType === 'we_feat' || $testType === 'web_feat') {
			return $testType;
		}
		// Vérifier que le testType existe
        if (!isset($this->product_table_map[$testType])) {
            throw new Exception("Unknown test type: " . htmlspecialchars($testType));
        }
        
        $testTypeMap = $this->product_table_map[$testType];
        
        // Si un produit est spécifié, le vérifier
        if ($product) {
            if (!isset($testTypeMap[$product])) {
                throw new Exception("Unknown product for test type: " . htmlspecialchars($product));
            }
            $tableName = $testTypeMap[$product];
        } else {
            // Si pas de produit, prendre le premier disponible
            // (ou lever une exception si produit obligatoire)
            $tableName = reset($testTypeMap); // Prend la première table
        }
        
        // Validation de sécurité supplémentaire
        if (!preg_match('/^[a-z0-9_]+$/', $tableName)) {
            throw new Exception("Invalid table name format");
        }
        
        return $tableName;
    }
    
    /**
     * Récupère les jobs disponibles pour une version et produit
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
            
            // Pour weWebSel, le Testtype dans la BD est le nom de la table (we_rc, we_hf, we_dev)
            // Pour les autres, c'est le testType (rc_x17, hf_x17, dev_x17, etc)
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
     * Récupère les derniers exécutions de tests pour un job
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
            
            // Pour weWebSel, le Testtype dans la BD est le nom de la table
            $dbTestType = $testType;
            if ($product == "weWebSel") {
                $dbTestType = $tableName;
            }
            
            // Cette requête récupère la dernière exécution pour chaque paramètre unique
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
            
            // Bind les paramètres réguliers
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }
            
            // Bind la limite en tant qu'entier
            $stmt->bindValue(':limit', min($limit, 500), PDO::PARAM_INT);
            
            $stmt->execute();
            return $stmt->fetchAll();
            
        } catch (Exception $e) {
            error_log("getLatestRunsForJob error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Récupère les détails d'une exécution spécifique
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
     * Marquer une exécution comme vérifiée
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
            
            // Si coché (Validated), changer le résultat en Flaky
            // TearDownFailed = 0, TearDownWarning = 1 (Flaky)
            if ($checked) {
                $query = "UPDATE `$tableName` SET checked = :checked, TearDownFailed = 0, TearDownWarning = 1 WHERE AutoID = :autoid";
            } else {
                // Si décoché, remettre le résultat en Failed
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
     * Obtenir les statistiques pour une version
     * 
     * @param string $testType
     * @param string|null $product
     * @return array
     */
    public function getStatistics(string $testType, ?string $product = null): array {
        try {
            $tableName = $this->getTableForTestType($testType, $product);
            
            // Pour weWebSel, le Testtype dans la BD est le nom de la table
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
     * Rechercher les exécutions échouées non vérifiées
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
            
            // Pour weWebSel, le Testtype dans la BD est le nom de la table
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
     * Exécuter une requête SELECT préparée
     * Méthode interne de commodité
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
     * Vérifier que la table existe
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
     * Obtenir les versions disponibles
     * 
     * @return array
     */
    public function getAvailableVersions(): array {
        return array_keys($this->product_table_map);
    }
    
    /**
     * Obtenir les produits disponibles pour une version
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
     * Obtenir le mapping complet (pour debug)
     * 
     * @return array
     */
    public function getMapping(): array {
        return $this->product_table_map;
    }
    
    /**
     * Obtenir les TestTypes disponibles pour un produit (basé sur les données réelles)
     * 
     * @param string $product
     * @return array
     */
    public function getAvailableTestTypesForProduct(string $product): array {
        // Vérifier le cache d'abord
        $cacheKey = "testTypes_{$product}";
        $cached = $this->cache->get($cacheKey);
        
        if ($cached !== null) {
            return $cached;
        }
        
        $availableTestTypes = [];
        
        // Pour weWebSel, retourner uniquement les versions X18, X17
        if ($product === "weWebSel") {
            $availableTestTypes = ['dev_x18', 'rc_x18', 'hf_x18', 'dev_x17', 'rc_x17', 'hf_x17'];
            // Cacher pendant 1 heure (3600 secondes)
            $this->cache->set($cacheKey, $availableTestTypes, 3600);
            return $availableTestTypes;
        }
        
        // Pour chaque version, vérifier si le produit existe dans le mapping.
        // Depuis la centralisation (config/versions_config.php), une branche
        // marquée 'active'/'future' pour ce produit est affichée directement,
        // sans requête de comptage : plus besoin d'attendre que des données
        // existent en base pour qu'une version nouvellement ajoutée apparaisse
        // dans le sélecteur (voir config/versions_config.php pour le statut
        // de chaque branche : 'active' | 'future' | 'retired').
        foreach ($this->product_table_map as $testType => $products) {
            if (isset($products[$product])) {
                $availableTestTypes[] = $testType;
            }
        }
        
        // Cacher le résultat pendant 1 heure (3600 secondes)
        $this->cache->set($cacheKey, $availableTestTypes, 3600);
        
        return $availableTestTypes;
    }
    
    /**
     * Récupérer les notes/tags pour un TestSet
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
            
            // Vérifier que la table _tags existe
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
     * Vider le cache (utile après les mises à jour)
     * @return int Nombre de fichiers supprimés
     */
    public function clearCache() {
        return $this->cache->flush();
    }
    
    /**
     * Nettoyer les fichiers de cache expiré
     * @return int Nombre de fichiers supprimés
     */
    public function cleanupCache() {
        return $this->cache->cleanup();
    }
    
    /**
     * Obtenir les stats du cache (pour debug)
     * @return array
     */
    public function getCacheStats() {
        return $this->cache->getStats();
    }
}
?>
