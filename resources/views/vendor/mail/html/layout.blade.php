<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<title>{{ config('app.name') }}</title>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<style>
@media only screen and (max-width: 600px) {
    .pad { padding-left: 24px !important; padding-right: 24px !important; }
}
@media only screen and (max-width: 500px) {
    .button { width: 100% !important; }
}
</style>
{{ $head ?? '' }}
</head>
<body>
<table class="wrapper" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="center">
    <table class="card" width="520" cellpadding="0" cellspacing="0" role="presentation">
        <tr>
            <td class="brand pad" align="center">
                <a href="{{ config('app.url') }}">{{ config('app.name') }}</a>
            </td>
        </tr>
        <tr>
            <td class="card-body pad">
                {{ Illuminate\Mail\Markdown::parse($slot) }}

                {{ $subcopy ?? '' }}
            </td>
        </tr>
        <tr>
            <td class="card-footer pad" align="center">
                <p>© {{ date('Y') }} {{ config('app.name') }}. Hak cipta dilindungi.</p>
            </td>
        </tr>
    </table>
</td>
</tr>
</table>
</body>
</html>