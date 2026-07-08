---
title: How to configure Gmail (SMTP)
---

For PeJota to send email through your Gmail account, you need to generate an
**App Password** in Google and use Gmail's SMTP settings.

## 1. Turn on 2-Step Verification

The App Password option is only available once 2-Step Verification is enabled.

1. Go to **Google Account → Security**.
2. Turn on **2-Step Verification**.

## 2. Generate an App Password

1. Still under **Security**, open **App passwords**.
2. Create a new password (choose "Other" and give it a name, e.g. `PeJota`).
3. Copy the 16-character password that is generated.

## 3. Fill in the email settings in PeJota

| Field | Value |
| --- | --- |
| Driver | SMTP |
| SMTP host | `smtp.gmail.com` |
| Port | `587` |
| Encryption | `TLS` |
| Username | your full Gmail address |
| Password | the 16-character **App Password** |
| From address | your Gmail address |

## 4. Test

Use the **Send test email** button at the top of the screen to confirm
everything is working.

> Tip: if the test fails, check that you used the App Password (not your
> regular account password) and that the port is 587 with TLS.
