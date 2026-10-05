# French Law & Contract Dispute Resolver (UNE Node 01)

Authoritative, zero-entropy French legal computation and cross-border commercial dispute engine built for autonomous agents, legal operations, and compliance platforms.

Connects directly to indexed local SQLite FTS5 stores of Légifrance (consolidated French Codes) and Judilibre (300,000+ Court of Cassation decisions) with constant sub-5ms deterministic response times.

---

## Remote MCP Endpoint

https://legal.une.education/france/mcp.php

Transport: Streamable HTTP POST (JSON-RPC 2.0)
Authentication: Public anonymous access (rate-limited at 30 req/min per IP) or Universal API Key via Authorization: Bearer sk_live_...

---

## Client Quickstart Configuration

### 1. Claude Desktop (claude_desktop_config.json)

```json
{
  "mcpServers": {
    "french-law-resolver": {
      "command": "npx",
      "args": [
        "-y",
        "mcp-remote",
        "https://legal.une.education/france/mcp.php"
      ]
    }
  }
}
```

### 2. Cursor IDE (.cursor/mcp.json)

```json
{
  "mcpServers": {
    "french-law-resolver": {
      "url": "https://legal.une.education/france/mcp.php"
    }
  }
}
```

---

## Exposed Deterministic Tools

| Tool Name | Governing French Law | Description |
| :--- | :--- | :--- |
| `resolve_article_status` | Légifrance Codified Statutes | Verifies statutory article applicability, active status (VIGUEURI, and exact consolidated legal text at date T. |
| `search_jurisprudence_precedent` | Judilibre / Cour de cassation | FTS5 ranked precedent search over Court of Cassation decisions with official ECLI keys and rulings. |
| `french_statute_of_limitations` | Art. 2224 C. civ. / L. 110-4 C. com. | Deterministic calculation of statutory time-bars, prescription deadlines, and expiry dates with SHA-256 seal. |
| `french_commercial_termination_risk` | Art. L. 442-1, II French Commercial Code | Computes mandatory notice requirements and gross margin damages for sudden termination of established B2B relationships. |
| `french_breach_remedy_notice` | Art. 1225 & 1226 French Civil Code | Generates compliant formal cure notices (Mise en demeure) enforcing statutory resolutory clauses. |
| `french_b2b_clause_validator` | Art. 1170/1171 C. civ. & L. 442-1 C. com. | Evaluates statutory unenforceability risk for abusive B2B terms, derisory caps, and significant imbalances. |

---

## Tool Calling Examples (cURL)

### 1. Sudden Termination Exposure (Art. L. 442-1, II)

```bash
curl -s -X POST https://legal.une.education/france/mcp.php \
  -H "Content-Type: application/json" \
  -d '{
    "jsonrpc": "2.0",
    "id": "req-1",
    "method": "tools/call",
    "params": {
      "name": "french_commercial_termination_risk",
      "arguments": {
        "relationship_duration_years": 8,
        "annual_gross_margin_eur": 150000,
        "dependency_rate_percent": 35,
        "contractual_notice_months": 3
      }
    }
  }'
```

### 2. Formal Cure Notice (Art. 1225 C. civ.)
```bash
curl -s -X POST https://legal.une.education/france/mcp.php \
  -H "Content-Type: application/json" \
  -d '{
    "jsonrpc": "2.0",
    "id": "req-2",
    "method": "tools/call",
    "params": {
      "name": "french_breach_remedy_notice",
      "arguments": {
        "creditor_name": "International Tech Solutions Ltd",
        "debtor_name": "Distributeur Francais SAS",
        "contract_reference": "SaaS-2025-04",
        "breach_type": "payment_default",
        "amount_due_eur": 38500,
        "remedy_period_days": 15
      }
    }
  }'
```

---

## Verification & Audit Sealing

Every computational decision, notice, and calculation returns a deterministic cryptographic proof:

```json
"audit_seal": {
    "algorithm": "SHA-256",
    "hash": "f52b13846966f030a0b08e8ecd4a2875c084d27688f4f5a977dbaa25dbd8026",
    "timestamp_utc": "2026-10-05T15:59:47Z"
}
```

---

## Operational Gateway & Settlement

- Discovery Tier: Free anonymous public tier (30 requests/min).
- Universal Developer Pass: 50,000 API requests cross-usable over Customs, Sino-Gateway, and French Law engines.
- Checkout URL: https://legal.une.education/checkout.php?pack=dev_50k&redirect=1
- Maintainer: UNE Infrastructure (ops@une.education)
