---
title: Cómo configurar Gmail (SMTP)
---

Para que PeJota envíe correos a través de tu cuenta de Gmail, necesitas generar
una **Contraseña de aplicación** en Google y usar los datos SMTP de Gmail.

## 1. Activa la verificación en dos pasos

La Contraseña de aplicación solo está disponible con la verificación en dos
pasos activada.

1. Ve a **Cuenta de Google → Seguridad**.
2. Activa la **Verificación en dos pasos**.

## 2. Genera una Contraseña de aplicación

1. Aún en **Seguridad**, abre **Contraseñas de aplicaciones**.
2. Crea una nueva contraseña (elige "Otra" y ponle un nombre, ej.: `PeJota`).
3. Copia la contraseña de 16 caracteres generada.

## 3. Completa la configuración de correo en PeJota

| Campo | Valor |
| --- | --- |
| Driver | SMTP |
| Host SMTP | `smtp.gmail.com` |
| Puerto | `587` |
| Cifrado | `TLS` |
| Usuario | tu dirección de Gmail completa |
| Contraseña | la **Contraseña de aplicación** de 16 caracteres |
| Dirección de envío | tu dirección de Gmail |

## 4. Prueba

Usa el botón **Enviar correo de prueba** en la parte superior de la pantalla
para confirmar que todo funciona.

> Consejo: si la prueba falla, verifica que usaste la Contraseña de aplicación
> (no la contraseña normal de la cuenta) y que el puerto es 587 con TLS.
