# matrix-register-bot

This bot provides a two-step-registration for matrix.

This is done in several steps:
- potential new user registers on a bot-provided side
- bot sends a message to prefined room with a registration notification.
- users in that room now can approve or decline the registration.
- The bot then uses the registration token to register the user or just drops the registration request.