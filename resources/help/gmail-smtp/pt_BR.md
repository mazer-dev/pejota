---
title: Como configurar o Gmail (SMTP)
---

Para o PeJota enviar e-mails pela sua conta Gmail, você precisa gerar uma
**Senha de App** no Google e usar os dados de SMTP do Gmail.

## 1. Ative a verificação em duas etapas

A Senha de App só fica disponível com a verificação em duas etapas ativa.

1. Acesse **Conta Google → Segurança**.
2. Ative **Verificação em duas etapas**.

## 2. Gere uma Senha de App

1. Ainda em **Segurança**, abra **Senhas de app**.
2. Crie uma nova senha (escolha "Outro" e dê um nome, ex.: `PeJota`).
3. Copie a senha de 16 caracteres gerada.

## 3. Preencha as configurações de e-mail no PeJota

| Campo | Valor |
| --- | --- |
| Driver | SMTP |
| Host SMTP | `smtp.gmail.com` |
| Porta | `587` |
| Criptografia | `TLS` |
| Usuário | seu endereço Gmail completo |
| Senha | a **Senha de App** de 16 caracteres |
| Endereço de envio | seu endereço Gmail |

## 4. Teste

Use o botão **Enviar e-mail de teste** no topo da tela para confirmar que
está tudo funcionando.

> Dica: se o teste falhar, confira se você usou a Senha de App (não a senha
> normal da conta) e se a porta é 587 com TLS.
