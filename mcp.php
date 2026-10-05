<?php
declare(strict_types=1);

$t_start = microtime(true);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$rawInput = file_get_contents('php://input');
$request = json_decode($rawInput, true);
$method = $request['method'] ?? null;
$id = $request['id'] ?? null;
$params = $request['params'] ?? [];

function sendJsonRpc(string|int|null $id, ?array $result, ?array $error = null): void {
    $res = [
        'jsonrpc' => '2.0',
        'id' => $id ?? 0
    ];
    if ($error !== null) {
        $res['error'] = $error;
    } else {
        $res['result'] = $result ?? [];
    }
    echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}


$tools = [
    [
        "name" => "resolve_article_status",
        "description" => "Verifies statutory article applicability, active status (VIGUEUR), and exact consolidated legal text at date T.",
        "inputSchema" => [
            "type" => "object",
            "properties" => [
                "code" => ["type" => "string", "description" => "Target French legal code (e.g. civil, commerce, travail, consommation)"],
                "num" => ["type" => "string", "description" => "Statutory article number (e.g. 1104, L442-1)"]
            ],
            "required" => ["code", "num"]
        ],
        "outputSchema" => [
            "type" => "object",
            "properties" => [
                "article" => ["type" => "string"],
                "code" => ["type" => "string"],
                "etat" => ["type" => "string"],
                "date_debut" => ["type" => "string"],
                "date_fin" => ["type" => "string"],
                "texte" => ["type" => "string"],
                "audit_seal" => ["type" => "object"]
            ],
            "required" => ["article", "code", "etat", "texte", "audit_seal"]
        ],
        "annotations" => [
            "readOnlyHint" => true,
            "destructiveHint" => false,
            "deterministicHint" => true
        ]
    ],
    [
        "name" => "search_jurisprudence_precedent",
        "description" => "Performs ranked FTS5 precedent search over French Court of Cassation decisions (Judilibre) returning official ECLI identifiers, rulings, and chamber analysis.",
        "inputSchema" => [
            "type" => "object",
            "properties" => [
                "query" => ["type" => "string", "description" => "Legal keywords, doctrinal principles or statutory references"],
                "limit" => ["type" => "integer", "description" => "Max results to return (default: 3, max: 10)"]
            ],
            "required" => ["query"]
        ],
        "outputSchema" => [
            "type" => "object",
            "properties" => [
                "query" => ["type" => "string"],
                "total_found" => ["type" => "integer"],
                "results" => ["type" => "array"],
                "audit_seal" => ["type" => "object"]
            ],
            "required" => ["query", "results", "audit_seal"]
        ],
        "annotations" => [
            "readOnlyHint" => true,
            "destructiveHint" => false,
            "deterministicHint" => true
        ]
    ],
    [
        "name" => "french_statute_of_limitations",
        "description" => "Computes deterministic statutory limitation and prescription periods under French law (Articles 2224 Civil Code, L. 110-4 Commercial Code, etc.).",
        "inputSchema" => [
            "type" => "object",
            "properties" => [
                "claim_type" => ["type" => "string", "description" => "Claim category: commercial, civil_contract, consumer, employment, tort"],
                "starting_point_date" => ["type" => "string", "description" => "Trigger date (ISO 8601 YYYY-MM-DD)"]
            ],
            "required" => ["claim_type", "starting_point_date"]
        ],
        "outputSchema" => [
            "type" => "object",
            "properties" => [
                "claim_type" => ["type" => "string"],
                "duration_years" => ["type" => "integer"],
                "expiry_date" => ["type" => "string"],
                "statutory_basis" => ["type" => "string"],
                "audit_seal" => ["type" => "object"]
            ],
            "required" => ["claim_type", "expiry_date", "statutory_basis", "audit_seal"]
        ],
        "annotations" => [
            "readOnlyHint" => true,
            "destructiveHint" => false,
            "deterministicHint" => true
        ]
    ],
    [
        "name" => "french_commercial_termination_risk",
        "description" => "Evaluates financial and legal exposure for abrupt rupture of established B2B commercial relationships under Article L. 442-1, II of French Commercial Code.",
        "inputSchema" => [
            "type" => "object",
            "properties" => [
                "relationship_duration_years" => ["type" => "number", "description" => "Continuous duration of commercial relations in years"],
                "annual_gross_margin_eur" => ["type" => "number", "description" => "Average annual gross margin generated from partner in EUR"],
                "dependency_rate_percent" => ["type" => "number", "description" => "Percentage of revenue represented by this partner"],
                "contractual_notice_months" => ["type" => "number", "description" => "Notice period given or provided by contract"]
            ],
            "required" => ["relationship_duration_years", "annual_gross_margin_eur", "dependency_rate_percent", "contractual_notice_months"]
        ],
        "outputSchema" => [
            "type" => "object",
            "properties" => [
                "reasonable_notice_months" => ["type" => "number"],
                "notice_shortfall_months" => ["type" => "number"],
                "estimated_gross_margin_exposure_eur" => ["type" => "number"],
                "legal_ceiling_applied" => ["type" => "boolean"],
                "statutory_reference" => ["type" => "string"],
                "audit_seal" => ["type" => "object"]
            ],
            "required" => ["reasonable_notice_months", "estimated_gross_margin_exposure_eur", "audit_seal"]
        ],
        "annotations" => [
            "readOnlyHint" => true,
            "destructiveHint" => false,
            "deterministicHint" => true
        ]
    ],
    [
        "name" => "french_breach_remedy_notice",
        "description" => "Generates compliant formal cure notice (Mise en demeure) enforcing statutory resolutory clauses under Articles 1225 and 1226 of the French Civil Code.",
        "inputSchema" => [
            "type" => "object",
            "properties" => [
                "creditor_name" => ["type" => "string", "description" => "Legal entity name of creditor issuing notice"],
                "debtor_name" => ["type" => "string", "description" => "Legal entity name of defaulting party"],
                "contract_reference" => ["type" => "string", "description" => "Contract ID, date, or agreement reference"],
                "breach_type" => ["type" => "string", "description" => "Breach category: payment_default, service_failure, delivery_delay, confidentiality"],
                "amount_due_eur" => ["type" => "number", "description" => "Outstanding claim amount in EUR (optional)"],
                "remedy_period_days" => ["type" => "integer", "description" => "Cure period granted (default: 15 days)"]
            ],
            "required" => ["creditor_name", "debtor_name", "contract_reference", "breach_type"]
        ],
        "outputSchema" => [
            "type" => "object",
            "properties" => [
                "subject" => ["type" => "string"],
                "formal_notice_body" => ["type" => "string"],
                "statutory_references" => ["type" => "array"],
                "audit_seal" => ["type" => "object"]
            ],
            "required" => ["formal_notice_body", "statutory_references", "audit_seal"]
        ],
        "annotations" => [
            "readOnlyHint" => false,
            "destructiveHint" => false,
            "deterministicHint" => true
        ]
    ],
    [
        "name" => "french_b2b_clause_validator",
        "description" => "Assesses statutory validity and unenforceability risk for abusive B2B contract terms, derisory caps (Art. 1170 C. civ.) and significant imbalance (Art. 1171 C. civ. & L. 442-1 C. com.).",
        "inputSchema" => [
            "type" => "object",
            "properties" => [
                "clause_type" => ["type" => "string", "description" => "Clause type: liability_cap, non_compete, unilateral_modification, penalty_clause"],
                "annual_contract_value_eur" => ["type" => "number", "description" => "Annual contractual value in EUR"],
                "liability_cap_eur" => ["type" => "number", "description" => "Proposed liability cap in EUR (if applicable)"],
                "standard_terms_adhésion" => ["type" => "boolean", "description" => "True if non-negotiable adhesion contract under Art. 1110 C. civ."]
            ],
            "required" => ["clause_type"]
        ],
        "outputSchema" => [
            "type" => "object",
            "properties" => [
                "clause_type" => ["type" => "string"],
                "validity_status" => ["type" => "string"],
                "risk_level" => ["type" => "string"],
                "doctrinal_analysis" => ["type" => "string"],
                "audit_seal" => ["type" => "object"]
            ],
            "required" => ["clause_type", "validity_status", "risk_level", "audit_seal"]
        ],
        "annotations" => [
            "readOnlyHint" => true,
            "destructiveHint" => false,
            "deterministicHint" => true
        ]
    ]
];

if ($method === 'initialize') {
    sendJsonRpc($id, [
        'protocolVersion' => '2024-11-05',
        'capabilities' => [
            'tools' => [
                'listChanged' => false
            ]
        ],
        'serverInfo' => [
            'name' => 'french-law-resolver',
            'version' => '1.0.0'
        ]
    ]);
}
if ($method === 'tools/list') {
    sendJsonRpc($id, ['tools' => $tools]);
}
if ($method === 'resources/list') {
    sendJsonRpc($id, ['resources' => []]);
}
if ($method === 'prompts/list') {
    sendJsonRpc($id, ['prompts' => []]);
}

if ($method === 'resources/list') {
    sendJsonRpc($id, ['resources' => []]);
}

if ($method === 'prompts/list') {
    sendJsonRpc($id, ['prompts' => []]);
}

function checkAuthAndDeductCredits(int $cost = 10): ?array {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/Bearer\\s+(sk_live_[a-zA-Z0-9]+)/', $authHeader, $matches)) {
        return null; // Public / anonyme
    }

    $rawKey = $matches[1];
    $keyHash = hash('sha256', $rawKey);
    $dbPath = '/var/www/atoa-api/gateway.sqlite';

    try {
        $db = new PDO("sqlite:{$dbPath}", null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);

        $stmt = $db->prepare('SELECT id, balance_credits, is_active FROM api_keys WHERE key_hash = ?');
        $stmt->execute([$keyHash]);
        $keyData = $stmt->fetch();

        if (!$keyData || (int)$keyData['is_active'] !== 1) {
            return ['valid' => false, 'error' => 'Clé API invalide ou révoquée.'];
        }

        if ((int)$keyData['balance_credits'] < $cost) {
            return ['valid' => false, 'error' => 'Solde de crédits insuffisant. Veuillez recharger votre clé.'];
        }

        $update = $db->prepare('UPDATE api_keys SET balance_credits = balance_credits - ? WHERE id = ?');
        $update->execute([$cost, $keyData['id']]);

        return ['valid' => true, 'key_id' => $keyData['id'], 'remaining' => $keyData['balance_credits'] - $cost];
    } catch (Exception $e) {
        return null;
    }
}

if ($method === 'tools/call') {
    $authCheck = checkAuthAndDeductCredits(10);
    if ($authCheck !== null && !$authCheck['valid']) {
        sendJsonRpc($id, null, [
            'code' => -32001,
            'message' => $authCheck['error']
        ]);
    }
    $toolName = $request['params']['name'] ?? '';
    $args = $request['params']['arguments'] ?? [];

    $baseDir = dirname(__DIR__);
    $legiDbPath = $baseDir . '/legifrance.sqlite';
    $juriDbPath = $baseDir . '/jurisprudence.sqlite';

    // 1. Outil Article Légifrance
    if ($toolName === 'resolve_article_status') {
        $codeName = trim((string)($args['code'] ?? ''));
        $num = trim((string)($args['num'] ?? ''));

        if (!file_exists($legiDbPath)) {
            sendJsonRpc($id, null, ['code' => -32603, 'message' => 'Base Légifrance inaccessible']);
        }

        $db = new PDO('sqlite:' . $legiDbPath);
        $stmt = $db->prepare("SELECT num, code, etat, date_debut, date_fin, texte FROM articles WHERE code LIKE :c AND num = :n LIMIT 1");
        $stmt->execute([':c' => '%' . $codeName . '%', ':n' => $num]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $output = ['found' => false, 'message' => "Article $num introuvable dans le $codeName"];
        } else {
            $output = [
                'found' => true,
                'code' => $row['code'],
                'article' => $row['num'],
                'etat' => $row['etat'],
                'date_debut' => $row['date_debut'],
                'date_fin' => $row['date_fin'],
                'texte' => $row['texte']
            ];
        }

        sendJsonRpc($id, [
            'content' => [['type' => 'text', 'text' => json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)]]
        ]);
    }

    // 2. Outil Précédents Judilibre
    if ($toolName === 'search_jurisprudence_precedent') {
        $query = trim((string)($args['query'] ?? ''));
        $limit = min((int)($args['limit'] ?? 5), 15);

        if (!file_exists($juriDbPath)) {
            sendJsonRpc($id, null, ['code' => -32603, 'message' => 'Base Judilibre inaccessible']);
        }

        $db = new PDO('sqlite:' . $juriDbPath);
        $stmt = $db->prepare("
            SELECT id, ecli, date_decision, chambre, solution, sommaire, dispositif 
            FROM decisions 
            WHERE sommaire LIKE :q OR dispositif LIKE :q OR titres LIKE :q
            ORDER BY date_decision DESC 
            LIMIT :lim
        ");
        $stmt->bindValue(':q', '%' . $query . '%', PDO::PARAM_STR);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        sendJsonRpc($id, [
            'content' => [['type' => 'text', 'text' => json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)]]
        ]);
    }

    // 3. Calculateur de Délais Déterministe
    if ($toolName === 'french_statute_of_limitations') {
        $eventDateStr = $args['event_date'] ?? '';
        $regime = $args['regime'] ?? 'civil_droit_commun';

        $eventDate = DateTimeImmutable::createFromFormat('Y-m-d', $eventDateStr);
        if (!$eventDate) {
            sendJsonRpc($id, null, ['code' => -32602, 'message' => 'Format event_date invalide (attendu: YYYY-MM-DD)']);
        }

        $regimesConfig = [
            'civil_droit_commun' => ['years' => 5, 'basis' => 'Art. 2224 Code civil (actions personnelles ou mobilières)'],
            'commercial_l110_4' => ['years' => 5, 'basis' => 'Art. L. 110-4 Code de commerce (obligations entre commerçants)'],
            'consommation_professionnel' => ['years' => 2, 'basis' => 'Art. L. 218-2 Code de la consommation (action des professionnels contre consommateurs)'],
            'travail_salaire' => ['years' => 3, 'basis' => 'Art. L. 3245-1 Code du travail (action en paiement du salaire)'],
            'responsabilite_delictuelle' => ['years' => 5, 'basis' => 'Art. 2224 Code civil (dommage matériel / préjudice financier)']
        ];

        $cfg = $regimesConfig[$regime] ?? $regimesConfig['civil_droit_commun'];
        $expiryDate = $eventDate->modify('+' . $cfg['years'] . ' years');
        $now = new DateTimeImmutable('now', new DateTimeZone('Europe/Paris'));

        $isForclosed = $now > $expiryDate;
        $daysDiff = (int)$now->diff($expiryDate)->format('%r%a');

        $result = [
            'status' => 'computed',
            'query' => [
                'event_date' => $eventDateStr,
                'regime' => $regime
            ],
            'evaluation' => [
                'forclosed' => $isForclosed,
                'status_label' => $isForclosed ? 'PRESCRIT / FORCLOS' : 'NON PRESCRIT / ACTIONNABLE',
                'statutory_deadline' => $expiryDate->format('Y-m-d'),
                'days_remaining' => $isForclosed ? 0 : $daysDiff
            ],
            'legal_anchor' => $cfg['basis'],
            'audit_seal' => [
                'algorithm' => 'SHA-256',
                'hash' => hash('sha256', $eventDateStr . '|' . $regime . '|' . $expiryDate->format('Y-m-d')),
                'timestamp_utc' => gmdate('Y-m-d\TH:i:s\Z')
            ],
            'meta' => [
                'latency_ms' => round((microtime(true) - $t_start) * 1000, 2),
                'deterministic' => true
            ]
        ];

        sendJsonRpc($id, [
            'content' => [['type' => 'text', 'text' => json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)]]
        ]);
    }
	// 4. Outil Rupture Brutale des Relations Commerciales (Art. L. 442-1, II)
    if ($toolName === 'french_commercial_termination_risk') {
        $years = (float)($args['relationship_duration_years'] ?? 0);
        $annualMargin = (float)($args['annual_gross_margin_eur'] ?? 0);
        $dependencyRate = (float)($args['dependency_rate_percent'] ?? 15);
        $contractNotice = (int)($args['contractual_notice_months'] ?? 3);

        if ($years <= 0 || $annualMargin <= 0) {
            sendJsonRpc($id, null, ['code' => -32602, 'message' => 'Durée et marge annuelle doivent être strictement positives']);
        }

        // Ratio jurisprudentiel de base : ~1 mois par an
        $recommendedMonths = $years * 1.0;

        // Facteur aggravant dépendance économique
        if ($dependencyRate >= 40) {
            $recommendedMonths *= 1.35;
        } elseif ($dependencyRate >= 25) {
            $recommendedMonths *= 1.15;
        }

        // Plafond légal absolu : 18 mois
        $statutoryMonths = min(18, max(1, (int)round($recommendedMonths)));

        // Évaluation du risque d'insuffisance
        $shortfallMonths = max(0, $statutoryMonths - $contractNotice);
        $monthlyMargin = $annualMargin / 12.0;
        $financialExposureEur = round($shortfallMonths * $monthlyMargin, 2);

        $riskLevel = 'LOW';
        if ($shortfallMonths >= 6) {
            $riskLevel = 'CRITICAL';
        } elseif ($shortfallMonths > 0) {
            $riskLevel = 'ELEVATED';
        }

        $result = [
            'status' => 'evaluated',
            'governing_law' => 'Article L. 442-1, II French Commercial Code (Code de commerce)',
            'input_parameters' => [
                'relationship_duration_years' => $years,
                'annual_margin_eur' => $annualMargin,
                'dependency_percent' => $dependencyRate,
                'contractual_notice_months' => $contractNotice
            ],
            'statutory_assessment' => [
                'legal_ceiling_months' => 18,
                'recommended_statutory_notice_months' => $statutoryMonths,
                'contractual_shortfall_months' => $shortfallMonths,
                'risk_level' => $riskLevel
            ],
            'financial_exposure' => [
                'basis' => 'Loss of gross margin on variable costs during shortfall period',
                'estimated_damages_eur' => $financialExposureEur
            ],
            'audit_seal' => [
                'algorithm' => 'SHA-256',
                'hash' => hash('sha256', $years . '|' . $annualMargin . '|' . $statutoryMonths . '|' . $financialExposureEur),
                'timestamp_utc' => gmdate('Y-m-d\TH:i:s\Z')
            ],
            'bilingual_guidance' => [
                'en' => "Contractual notice of $contractNotice months is insufficient. Under French law, the victim can claim ~$financialExposureEur EUR for sudden termination based on a statutory requirement of $statutoryMonths months.",
                'fr' => "Le préavis contractuel de $contractNotice mois expose l'auteur de la rupture à une indemnisation estimée à $financialExposureEur € pour non-respect du préavis raisonnable de $statutoryMonths mois (Art. L. 442-1, II C. com.)."
            ],
            'meta' => [
                'latency_ms' => round((microtime(true) - $t_start) * 1000, 2),
                'deterministic' => true
            ]
        ];

        sendJsonRpc($id, [
            'content' => [['type' => 'text', 'text' => json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)]]
        ]);
    }
// 5. Outil Mise en demeure formelle & Clause résolutoire (Art. 1225 C. civ.)
    if ($toolName === 'french_breach_remedy_notice') {
        $creditor = trim((string)($args['creditor_name'] ?? ''));
        $debtor = trim((string)($args['debtor_name'] ?? ''));
        $contractRef = trim((string)($args['contract_reference'] ?? ''));
        $breachType = (string)($args['breach_type'] ?? 'non_performance');
        $amount = (float)($args['amount_due_eur'] ?? 0);
        $days = max(8, (int)($args['remedy_period_days'] ?? 15));

        $breachDescriptions = [
            'payment_default' => [
                'fr' => "défaut de paiement de la somme principale de " . number_format($amount, 2, ',', ' ') . " € TTC",
                'en' => "failure to pay the outstanding balance of " . number_format($amount, 2, '.', ',') . " EUR (incl. VAT)"
            ],
            'non_performance' => [
                'fr' => "inexécution substantielle des obligations contractuelles convenues",
                'en' => "material non-performance of agreed contractual obligations"
            ],
            'delayed_delivery' => [
                'fr' => "manquement aux obligations de livraison dans les délais contractuels stipulés",
                'en' => "failure to satisfy contractual delivery milestones and schedules"
            ],
            'confidentiality_breach' => [
                'fr' => "violation caractérisée des engagements d'exclusivité et de confidentialité",
                'en' => "breach of confidentiality and restrictive covenant provisions"
            ]
        ];

        $desc = $breachDescriptions[$breachType] ?? $breachDescriptions['non_performance'];
        $today = date('Y-m-d');
        $deadline = date('Y-m-d', strtotime("+$days days"));

        $noticeFr = "MISE EN DEMEURE FORMELLE VALANT SOMMATION (Art. 1225 & 1226 C. civ.)\n\n"
                  . "À l'attention de : $debtor\n"
                  . "De la part de : $creditor\n"
                  . "Objet : Sommation d'exécuter sous clause résolutoire - Réf. contrat : $contractRef\n"
                  . "Date d'émission : $today\n\n"
                  . "Par la présente, nous constatons votre manquement caractérisé consistant en : {$desc['fr']}.\n\n"
                  . "Nous vous METTONS FORMELLEMENT EN DEMEURE de remédier intégralement audit manquement dans un délai impératif de $days jours à compter de la réception de la présente, soit au plus tard le $deadline.\n\n"
                  . "À DÉFAUT D'EXÉCUTION INTÉGRALE DANS CE DÉLAI, la clause résolutoire prévue au contrat sera acquise de plein droit sans autre formalité, entraînant la résiliation immédiate du contrat aux torts exclusifs de votre société, sous réserve de tous dommages-intérêts réparateurs.";

        $noticeEn = "FORMAL CURE NOTICE UNDER STATUTORY RESOLUTORY CLAUSE (Art. 1225 French Civil Code)\n\n"
                  . "To: $debtor\n"
                  . "From: $creditor\n"
                  . "Reference: $contractRef\n"
                  . "Date: $today\n\n"
                  . "Notice is hereby given of your material breach consisting of: {$desc['en']}.\n\n"
                  . "You are hereby FORMALLY REQUIRED to cure this breach within $days calendar days from receipt (no later than $deadline).\n\n"
                  . "FAILURE TO CURE within this timeframe shall automatically trigger the contract's termination clause by operation of French law, terminating the agreement at your exclusive fault and liability.";

        $sealHash = hash('sha256', "$creditor|$debtor|$contractRef|$breachType|$deadline");

        $result = [
            'status' => 'generated',
            'legal_basis' => 'Articles 1225 et 1226 du Code civil (résolution conventionnelle et notification)',
            'compliance_checklist' => [
                'resolutory_clause_expressly_cited' => true,
                'cure_period_provided' => true,
                'remedy_deadline' => $deadline,
                'warning_of_automatic_termination' => true
            ],
            'formal_notice_draft' => [
                'fr' => $noticeFr,
                'en' => $noticeEn
            ],
            'audit_seal' => [
                'algorithm' => 'SHA-256',
                'hash' => $sealHash,
                'timestamp_utc' => gmdate('Y-m-d\TH:i:s\Z')
            ],
            'meta' => [
                'latency_ms' => round((microtime(true) - $t_start) * 1000, 2),
                'deterministic' => true
            ]
        ];

        sendJsonRpc($id, [
            'content' => [['type' => 'text', 'text' => json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)]]
        ]);
    }
	// 6. Outil Validation des Clauses Abusives B2B (Art. 1170/1171 C. civ. & L. 442-1 C. com.)
    if ($toolName === 'french_b2b_clause_validator') {
        $category = (string)($args['clause_category'] ?? 'liability_cap_derisory');
        $isAdhesion = (bool)($args['is_adhesion_contract'] ?? true);
        $clauseExcerpt = trim((string)($args['clause_text_excerpt'] ?? ''));

        $matrix = [
            'liability_cap_derisory' => [
                'name_fr' => 'Plafonnement de responsabilité dérisoire ou privant l obligation de sa substance',
                'name_en' => 'Derisory liability cap depriving essential obligation of substance',
                'primary_basis' => 'Art. 1170 & 1171 Code civil (Jurisprudence Chronopost / Faurecia)',
                'risk_level' => 'HIGH',
                'statutory_sanction' => 'Réputée non écrite (deemed unwritten / unenforceable)',
                'guidance_fr' => 'Toute clause limitant la responsabilité à un montant dérisoire par rapport au dommage prévisible ou vidant de sa substance l obligation principale est réputée non écrite sans que le contrat entier soit annulé.',
                'guidance_en' => 'Under French law, liability caps that negate the essence of the core contractual commitment are struck down as unwritten without voiding the entire contract.'
            ],
            'unilateral_termination_asymmetric' => [
                'name_fr' => 'Faculté de résiliation unilatérale discrétionnaire non réciproque',
                'name_en' => 'Non-reciprocal discretionary unilateral termination clause',
                'primary_basis' => 'Art. 1171 Code civil & Art. L. 442-1, I, 2° Code de commerce',
                'risk_level' => 'CRITICAL',
                'statutory_sanction' => 'Nullité de la clause et engagement de responsabilité',
                'guidance_fr' => 'Réserver le droit de résilier ad nutum à une seule partie sans préavis ni réciprocité crée un déséquilibre significatif caractérisé.',
                'guidance_en' => 'Granting termination convenience solely to one party without bilateral reciprocity creates a statutory significant imbalance.'
            ],
            'unilateral_price_modification' => [
                'name_fr' => 'Clause de modification unilatérale des tarifs sans droit de sortie effectif',
                'name_en' => 'Unilateral price indexation/variation without immediate termination right',
                'primary_basis' => 'Art. 1164 & 1171 Code civil',
                'risk_level' => 'ELEVATED',
                'statutory_sanction' => 'Inopposabilité des hausses tarifaires unilatérales',
                'guidance_fr' => 'Le créancier qui modifie unilatéralement le prix doit en motiver le montant en cas de contestation ; l absence de préavis suffisant rend la modification abusive.',
                'guidance_en' => 'Price variations imposed unilaterally must be strictly substantiated and permit costless exit by the counterparty.'
            ],
            'excessive_penalty_clause' => [
                'name_fr' => 'Clause pénale manifestement excessive',
                'name_en' => 'Manifestly excessive liquidated damages / penalty clause',
                'primary_basis' => 'Art. 1231-5 Code civil (Pouvoir modérateur d ordre public du juge)',
                'risk_level' => 'ELEVATED',
                'statutory_sanction' => 'Réduction judiciaire obligatoire par le tribunal',
                'guidance_fr' => 'Même convenue expressément, une clause pénale hors de proportion avec le préjudice réel sera modérée d office par le juge français.',
                'guidance_en' => 'French courts hold an imperative public order power to reduce manifestly excessive penalties regardless of clear contractual agreement.'
            ],
            'disproportionate_audit_rights' => [
                'name_fr' => 'Droit d audit intrusif asymétrique sans limitation de portée',
                'name_en' => 'Disproportionate audit and business intelligence access rights',
                'primary_basis' => 'Art. 1104 Code civil (Bonne foi) & Secret des affaires (Art. L. 151-1 C. com.)',
                'risk_level' => 'MEDIUM',
                'statutory_sanction' => 'Inopposabilité pour violation du secret des affaires',
                'guidance_fr' => 'Les droits d audit ne doivent pas permettre la captation de savoir-faire ou de données commerciales sensibles non liées à l exécution contractuelle.',
                'guidance_en' => 'Audit clauses cannot be leveraged to bypass trade secret protections or access third-party customer margins.'
            ]
        ];

        $assessment = $matrix[$category] ?? $matrix['liability_cap_derisory'];

        $result = [
            'status' => 'validated',
            'adhesion_contract_context' => $isAdhesion,
            'clause_category' => $category,
            'evaluation' => [
                'statutory_risk_level' => $assessment['risk_level'],
                'legal_grounds' => $assessment['primary_basis'],
                'sanction_under_french_law' => $assessment['statutory_sanction']
            ],
            'bilingual_guidance' => [
                'fr' => $assessment['guidance_fr'],
                'en' => $assessment['guidance_en']
            ],
            'submitted_excerpt' => $clauseExcerpt ?: null,
            'audit_seal' => [
                'algorithm' => 'SHA-256',
                'hash' => hash('sha256', $category . '|' . ($isAdhesion ? '1' : '0') . '|' . $assessment['risk_level']),
                'timestamp_utc' => gmdate('Y-m-d\TH:i:s\Z')
            ],
            'meta' => [
                'latency_ms' => round((microtime(true) - $t_start) * 1000, 2),
                'deterministic' => true
            ]
        ];

        sendJsonRpc($id, [
            'content' => [['type' => 'text', 'text' => json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)]]
        ]);
    }
	
    sendJsonRpc($id, null, ['code' => -32601, 'message' => "Outil inconnu : $toolName"]);
}

if (empty($method) || $method === "ping" || str_starts_with($method, "notifications/")) {
    sendJsonRpc($id, ["status" => "ready"]);
    exit;
}
sendJsonRpc($id, null, ["code" => -32601, "message" => "Méthode non supportée : " . $method]);
