<!doctype html>
<html lang="id">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ $status }} · {{ config('app.name','Gym') }}</title>
<style>body{font-family:system-ui,sans-serif;margin:0;background:#f6f7fb;color:#253047;min-height:100vh;display:grid;place-items:center}.error-page{max-width:440px;margin:24px;padding:32px;text-align:center;background:#fff;border-radius:12px}.status{font-size:40px;font-weight:700;color:#5145b5}p{line-height:1.6}a{display:inline-block;padding:10px 18px;border-radius:6px;background:#5145b5;color:#fff;text-decoration:none}</style></head>
<body><main class="error-page"><div class="status">{{ $status }}</div><p>{{ $message }}</p><a href="{{ url('/') }}">Kembali ke halaman utama</a></main></body>
</html>
