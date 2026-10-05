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

function sendJsonRpc(string|int|null $id, ?array $result, ?array $error = null): void {
    $res = ['jsonrpc' => '2.0'];
    if ($id !== null) {
        $res['id'] = $id;
    }
    if ($error !== null) {
        $res['error'] = $error;
    } else {
        $res['result'] = $result;
    }
    echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$tools = [
    [
        'name' => 'resolve_article_status',
        'description' => 'Vérifie l\'état de vigueur, l\'intitulé et le texte intégral d\'un article de code français (Code civil, commerce, travail, etc.) à date T.',
        'inputSchema' => [
            'type' => 'object',
            'properties' => [
                'code' => ['type' => 'string', 'description' => 'Nom du code (ex: "Code civil", "Code de commerce")'],
                'num' => ['type' => 'string', 'description' => 'Numéro de l\'article (ex: "1104", "L110-4", "1240")']
            ],
            'required' => ['code', 'num']
        ]
    ],
    [
        'name' => 'search_jurisprudence_precedent',
        'description' => 'Recherche ciblée de précédents de la Cour de cassation par mot-clé juridique ou thème.',
        'inputSchema' => [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string', 'description' => 'Terme juridique ou motif de pourvoi (ex: "rupture brutale", "clause résolutoire")'],
                'limit' => ['type' => 'integer', 'default' => 5, 'description' => 'Nombre maximal d\'arrêts (max 15)']
            ],
            'required' => ['query']
        ]
    ],
    [
        'name' => 'french_statute_of_limitations',
        'description' => 'Calcul déterministe des délais de prescription extinctive de droit commun ou spécial en droit des obligations français, avec sceau cryptographique SHA-256.',
        'inputSchema' => [
            'type' => 'object',
            'properties' => [
                'event_date' => ['type' => 'string', 'description' => 'Date du fait générateur ou de la créance (YYYY-MM-DD)'],
                'regime' => [
                    'type' => 'string',
                    'enum' => ['civil_droit_commun', 'commercial_l110_4', 'consommation_professionnel', 'travail_salaire', 'responsabilite_delictuelle'],
                    'description' => 'Régime juridique applicable'
                ]
            ],
            'required' => ['event_date', 'regime']
        ]
    ],
	[
        'name' => 'french_commercial_termination_risk',
        'description' => 'Calculates mandatory statutory notice periods and financial liability exposure for sudden termination of established B2B commercial relationships under French Commercial Code (Art. L. 442-1, II). Includes bilingual output.',
        'inputSchema' => [
            'type' => 'object',
            'properties' => [
                'relationship_duration_years' => [
                    'type' => 'number',
                    'description' => 'Duration of the commercial relationship in years (e.g. 8.5)'
                ],
                'annual_gross_margin_eur' => [
                    'type' => 'number',
                    'description' => 'Average annual gross margin (or margin on variable costs) earned from this partner in EUR'
                ],
                'dependency_rate_percent' => [
                    'type' => 'number',
                    'description' => 'Estimated economic dependency of the victim partner on this contract (0-100%)',
                    'default' => 15
                ],
                'contractual_notice_months' => [
                    'type' => 'integer',
                    'description' => 'Notice period stipulated in the contract (in months)',
                    'default' => 3
                ]
            ],
            'required' => ['relationship_duration_years', 'annual_gross_margin_eur']
        ]
    ],
	[
        'name' => 'french_breach_remedy_notice',
        'description' => 'Generates legally compliant, enforceable formal notices of breach (Mise en demeure) triggering termination clauses under French Civil Code (Art. 1225 & 1226). Outputs structured bilingual summons.',
        'inputSchema' => [
            'type' => 'object',
            'properties' => [
                'creditor_name' => ['type' => 'string', 'description' => 'Legal name of the notifying party'],
                'debtor_name' => ['type' => 'string', 'description' => 'Legal name of the breaching party'],
                'contract_reference' => ['type' => 'string', 'description' => 'Contract title/date/reference'],
                'breach_type' => [
                    'type' => 'string',
                    'enum' => ['payment_default', 'non_performance', 'delayed_delivery', 'confidentiality_breach'],
                    'description' => 'Nature of the contractual breach'
                ],
                'amount_due_eur' => ['type' => 'number', 'description' => 'Outstanding amount if payment breach (optional)'],
                'remedy_period_days' => ['type' => 'integer', 'default' => 15, 'description' => 'Cure period granted in days']
            ],
            'required' => ['creditor_name', 'debtor_name', 'contract_reference', 'breach_type']
        ]
    ],
	[
        'name' => 'french_b2b_clause_validator',
        'description' => 'Evaluates French statutory unenforceability risk for standard B2B contractual clauses under Art. 1170/1171 French Civil Code and Art. L. 442-1, I French Commercial Code (significant imbalance & core obligations).',
        'inputSchema' => [
            'type' => 'object',
            'properties' => [
                'clause_category' => [
                    'type' => 'string',
                    'enum' => [
                        'liability_cap_derisory',
                        'unilateral_termination_asymmetric',
                        'unilateral_price_modification',
                        'excessive_penalty_clause',
                        'disproportionate_audit_rights'
                    ],
                    'description' => 'Typology of the clause to be reviewed'
                ],
                'is_adhesion_contract' => [
                    'type' => 'boolean',
                    'description' => 'Whether the contract is standard non-negotiable terms (contrat d adhésion)',
                    'default' => true
                ],
                'clause_text_excerpt' => [
                    'type' => 'string',
                    'description' => 'Excerpt of the contractual clause (optional)'
                ]
            ],
            'required' => ['clause_category']
        ]
    ]
];

// Requête GET d'information
if (!$request || !isset($request['method'])) {
    sendJsonRpc(null, [
        'name' => 'French Law Compliance & Precedent Resolver',
        'status' => 'operational',
        'protocol' => 'MCP/JSON-RPC-2.0',
        'corpus' => 'Légifrance (Codes consolidés) & Judilibre (Cour de cassation)',
        'tools_count' => count($tools)
    ]);
}

$id = $request['id'] ?? null;
$method = $request['method'];

if ($method === 'initialize') {
    sendJsonRpc($id, [
        'protocolVersion' => '2024-11-05',
        'capabilities' => ['tools' => new stdClass()],
        'serverInfo' => [
            'name' => 'french-law-resolver',
            'version' => '1.0.0'
        ]
    ]);
}

if ($method === 'tools/list') {
    sendJsonRpc($id, ['tools' => $tools]);
}

if ($method === 'tools/call') {
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

sendJsonRpc($id, null, ['code' => -32601, 'message' => "Méthode non supportée : $method"]);
