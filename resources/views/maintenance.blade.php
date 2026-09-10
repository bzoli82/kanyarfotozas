<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $brand }} — karbantartás</title>
    <style>
        :root { color-scheme: dark; }
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px;
            background: #0d0d0d; color: #f0f0f0;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        .card { max-width: 30rem; text-align: center; }
        .badge {
            display: inline-block; padding: 4px 12px; border: 1px solid #2a2a2a; border-radius: 999px;
            font-size: 11px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: #8a8a8a;
        }
        h1 { margin: 20px 0 12px; font-size: 22px; letter-spacing: -0.01em; }
        p { margin: 0; color: #b3b3b3; line-height: 1.6; font-size: 15px; }
        .brand { margin-top: 28px; font-weight: 800; letter-spacing: -0.02em; font-size: 15px; color: #8a8a8a; }
        .brand b { color: #e63946; }
    </style>
</head>
<body>
    <div class="card">
        <span class="badge">Karbantartás</span>
        <h1>Mindjárt jövünk</h1>
        <p>{{ $message }}</p>
        <div class="brand">{{ $brand }}</div>
    </div>
</body>
</html>
