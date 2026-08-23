<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tu cuenta en VillaDonq</title>
</head>
<body style="font-family: Arial, sans-serif; padding: 20px; max-width: 600px; margin: 0 auto;">
    <h1 style="color: #333;">Bienvenido a VillaDonq</h1>

    <p style="font-size: 16px; color: #555;">
        Hola <strong>{{ $user->name }} {{ $user->last_name }}</strong>,
    </p>

    <p style="font-size: 16px; color: #555;">
        Se ha creado una cuenta para ti como representante en el sistema de VillaDonq. A continuación encontrarás tus datos de acceso.
    </p>

    <div style="background-color: #f5f5f5; border-radius: 8px; padding: 20px; margin: 30px 0;">
        <p style="margin: 0 0 10px; font-size: 15px; color: #333;">
            <strong>Correo de acceso:</strong>
            <span style="color: #555;">{{ $user->email }}</span>
        </p>
        <p style="margin: 0; font-size: 15px; color: #333;">
            <strong>Contraseña:</strong>
            <span style="color: #555;">{{ $plainPassword }}</span>
        </p>
    </div>

    <div style="margin: 30px 0; text-align: center;">
        <a href="{{ url('/') }}" style="background-color: #4CAF50; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-size: 16px; display: inline-block;">
            Iniciar sesión
        </a>
    </div>

    <p style="font-size: 14px; color: #888;">
        Por seguridad, te recomendamos cambiar tu contraseña después de iniciar sesión.
    </p>

    <hr style="border: none; border-top: 1px solid #eee; margin: 30px 0;">

    <p style="font-size: 12px; color: #aaa;">
        VillaDonq - Sistema de Gestión Escolar
    </p>
</body>
</html>
