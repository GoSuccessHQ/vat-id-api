<?php

declare(strict_types=1);

/**
 * Refreshes the snapshots in resources/specs from the services: the VIES
 * specification, the eVATR specification and eVATR's status messages, which
 * EvatrStatus ships. The weekly spec-drift workflow runs it and fails when a
 * snapshot changed, so a change at the BZSt or the Commission is noticed.
 */

use GoSuccess\VatId\Http\CurlHttpClient;
use GoSuccess\VatId\Http\Method;
use GoSuccess\VatId\Http\Request;

require __DIR__ . '/../vendor/autoload.php';

$sources = [
    'vies.yaml' => 'https://ec.europa.eu/assets/taxud/vow-information/swagger_publicVAT.yaml',
    'evatr.json' => 'https://api.evatr.vies.bzst.de/api-docs',
    'evatr-statusmeldungen.json' => 'https://api.evatr.vies.bzst.de/v1/info/statusmeldungen',
];

$http = new CurlHttpClient();

foreach ($sources as $file => $url) {
    $response = $http->send(new Request(Method::Get, $url, ['Accept' => 'application/json, application/yaml, */*']));

    if (!$response->isSuccessful) {
        fwrite(STDERR, "{$url}: HTTP {$response->statusCode}\n");

        exit(1);
    }

    // Line endings as committed (.gitattributes: eol=lf); VIES serves CRLF.
    $body = str_replace("\r\n", "\n", $response->body);

    if (str_ends_with($file, '.json')) {
        // Pretty and stable, so a diff shows what changed; the build stamp of eVATR's version is dropped.
        $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

        if (is_array($decoded) && is_array($decoded['info'] ?? null)) {
            unset($decoded['info']['version']);
        }

        $body = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
    }

    file_put_contents(__DIR__ . "/../resources/specs/{$file}", $body);
    echo "{$file}\n";
}
