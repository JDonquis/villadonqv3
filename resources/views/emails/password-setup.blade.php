<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Establece tu contraseña</title>
</head>
<body style="font-family: Arial, sans-serif; padding: 20px; max-width: 600px; margin: 0 auto;">
    <h1 style="color: #333;">Bienvenido a VillaDonq</h1>
    
    <p style="font-size: 16px; color: #555;">
        Hola <strong>{{ $user->name }} {{ $user->last_name }}</strong>,
    </p>
    
    <p style="font-size: 16px; color: #555;">
        Se ha creado una cuenta para ti en el sistema de VillaDonq. Para completar tu registro, necesitas establecer tu contraseña.
    </p>
    
    <div style="margin: 30px 0; text-align: center;">
        <a href="{{ $setupUrl }}" style="background-color: #4CAF50; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-size: 16px; display: inline-block;">
            Establecer mi contraseña
        </a>
    </div>
    
    <p style="font-size: 14px; color: #888;">
        Este enlace expire en 12 horas. Si no solicitaste este correo, por favor ignóralo.
    </p>
    
    <hr style="border: none; border-top: 1px solid #eee; margin: 30px 0;">
    
    <p style="font-size: 12px; color: #aaa;">
        VillaDonq - Sistema de Gestión Escolar
    </p>
</body>
</html>