---
title: Formato do número da fatura
---

Monte o número da fatura usando **tokens de data** e **zeros** para a sequência.

| Token | Significado | Exemplo |
| --- | --- | --- |
| `y` | Ano com 2 dígitos | 26 |
| `Y` | Ano com 4 dígitos | 2026 |
| `m` | Mês (01–12) | 03 |
| `d` | Dia (01–31) | 07 |
| `000` | Sequência (use quantos zeros precisar) | 001 |

## Exemplos

- `ym000` → 2603001 (reinicia mensalmente)
- `Ym000` → 202603001 (reinicia mensalmente, ano com 4 dígitos)
- `Y000` → 2026001 (reinicia anualmente)
- `ymd00` → 26030701 (reinicia diariamente)

> A sequência volta para 1 automaticamente quando o período da data muda.
