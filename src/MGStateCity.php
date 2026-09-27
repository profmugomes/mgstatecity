<?php
// Copyright (c) 2026 Murilo Gomes <profmugomes.com.br>. All Rights Reserved. (https://profmugomes.com.br)

// Licensed under the PolyForm Perimeter License 1.0.1.
// See LICENSE.md for details.

declare(strict_types=1);

namespace MGStateCity;

class MGStateCity
{
    private string $cacheDir = '';
    private int $cacheTTL = 604800; // 7 dias

    public function setCacheDir(string $path, int $permission = 0777): void
    {
        if (!is_dir($path)) {
            mkdir($path, $permission, true);
        }

        $this->cacheDir = $path;
    }

    public function setCacheTTL(int $value): void
    {
        $this->cacheTTL = $value;
    }

    private function getCache(string $key, int $ttl): ?array
    {
        $file = $this->cacheDir . '/' . md5($key) . '.json';

        if (!file_exists($file)) {
            return null;
        }

        if (time() - filemtime($file) > $ttl) {
            unlink($file);
            return null;
        }

        return json_decode(file_get_contents($file), true);
    }

    private function setCache(string $key, array $data): void
    {
        if (empty($this->cacheDir)) {
            $this->setCacheDir(dirname(__FILE__, 2) . '/cache', 0755);
        }

        $file = $this->cacheDir . '/' . md5($key) . '.json';
        file_put_contents($file, json_encode($data));
    }

    private function getHTTPJSON(string $url): array
    {
        $cached = $this->getCache($url, $this->cacheTTL);
        if ($cached !== null) {
            return [$cached, null];
        }

        $response = @file_get_contents($url);

        if ($response === false) {
            return [null, 'Erro ao acessar API'];
        }

        $data = json_decode($response, true);

        if ($data === null) {
            return [null, 'Erro ao decodificar JSON'];
        }

        $this->setCache($url, $data);

        return [$data, null];
    }

    public function listarEstados(): array
    {
        $url = 'https://servicodados.ibge.gov.br/api/v1/localidades/estados';

        list($data, $erro) = $this->getHTTPJSON($url);
        if ($erro) return [null, $erro];

        $estados = [];

        foreach ($data as $row) {
            if (!empty($row['nome']) && !empty($row['sigla'])) {
                $estados[] = $row['sigla'] . ' - ' . $row['nome'];
            }
        }

        return [$estados, null];
    }

    public function listMunicipios(string $uf): array
    {
        $url = sprintf('https://servicodados.ibge.gov.br/api/v1/localidades/estados/%s/municipios', urlencode($uf));

        list($data, $erro) = $this->getHTTPJSON($url);
        if ($erro) return [null, $erro];

        $municipios = [];

        foreach ($data as $item) {
            if (isset($item['nome'])) {
                $municipios[] = $item['nome'];
            }
        }

        return [$municipios, null];
    }

    public function buscarPorNome(string $nome, string $uf): array
    {
        $url = sprintf('https://servicodados.ibge.gov.br/api/v1/localidades/estados/%s/municipios', urlencode($uf));

        list($data, $erro) =  $this->getHTTPJSON($url);
        if ($erro) return [null, null, $erro];

        $municipioID = 0;

        foreach ($data as $m) {
            if (strcasecmp($m['nome'], $nome) === 0) {
                $municipioID = $m['id'];
                break;
            }
        }

        if ($municipioID === 0) {
            return [null, null, "Município {$nome}/{$uf} não encontrado"];
        }

        $coordsURL = sprintf('https://servicodados.ibge.gov.br/api/v3/malhas/municipios/%d/metadados', $municipioID);

        list($coordsData, $erro) = $this->getHTTPJSON($coordsURL);
        if ($erro) return [null, null, $erro];

        if (empty($coordsData)) {
            return [null, null, "Coordenadas não encontradas para o ID {$municipioID}"];
        }

        $lat = strval($coordsData[0]['centroide']['latitude']);
        $lon = strval($coordsData[0]['centroide']['longitude']);

        return [$lat, $lon, null];
    }
}
