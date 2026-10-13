<?php

namespace Tests\Support;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

/**
 * Memeriksa data terhadap skema pada openapi.yaml (sumber kebenaran kontrak).
 * Skema OpenAPI 3.1 adalah JSON Schema 2020-12, sehingga divalidasi langsung.
 */
final class OpenApiContract
{
    private object $doc;

    private Validator $validator;

    public function __construct(?string $file = null)
    {
        $file ??= base_path('openapi.yaml');
        $this->doc = Yaml::parseFile($file, Yaml::PARSE_OBJECT_FOR_MAP);
        $this->validator = new Validator;
    }

    /** @return list<string> operationId seluruh operasi */
    public function operationIds(): array
    {
        $ids = [];
        foreach ($this->doc->paths as $item) {
            foreach ($item as $operation) {
                $ids[] = $operation->operationId;
            }
        }

        return $ids;
    }

    /**
     * Validasi badan JSON terhadap skema respons operasi untuk status tertentu.
     *
     * @param  mixed  $data  hasil json_decode($body, false) (objek, bukan array asosiatif)
     * @return list<string> daftar pelanggaran (kosong = valid)
     */
    public function validateResponse(string $operationId, int $status, mixed $data): array
    {
        $schema = $this->responseSchema($operationId, $status);

        return $this->validate($schema, $data);
    }

    /** @return list<string> */
    public function validateAgainstSchema(string $schemaName, mixed $data): array
    {
        return $this->validate((object) ['$ref' => '#/components/schemas/'.$schemaName], $data);
    }

    public function hasResponse(string $operationId, int $status): bool
    {
        return isset($this->operation($operationId)->responses->{(string) $status});
    }

    /** Nama skema `Error`/`*Response` yang dipakai respons tersebut (untuk diagnosis). */
    public function responseSchemaName(string $operationId, int $status): ?string
    {
        $schema = $this->responseSchema($operationId, $status);

        return isset($schema->{'$ref'}) ? basename($schema->{'$ref'}) : null;
    }

    private function validate(object $schema, mixed $data): array
    {
        $root = (object) [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            'components' => $this->doc->components,
        ];
        foreach ((array) $schema as $key => $value) {
            $root->{$key} = $value;
        }

        $result = $this->validator->validate($data, $root);
        if ($result->isValid()) {
            return [];
        }

        $errors = (new ErrorFormatter)->format($result->error(), false);

        return array_map(
            fn (string $path, array|string $messages) => $path.': '.implode('; ', (array) $messages),
            array_keys($errors),
            array_values($errors),
        );
    }

    private function responseSchema(string $operationId, int $status): object
    {
        $response = $this->operation($operationId)->responses->{(string) $status} ?? null;
        if ($response === null) {
            throw new RuntimeException("Respons {$status} tidak didefinisikan untuk {$operationId}");
        }

        if (isset($response->{'$ref'})) {
            $response = $this->resolve($response->{'$ref'});
        }

        $json = $response->content->{'application/json'} ?? null;
        if ($json === null) {
            throw new RuntimeException("Respons {$status} {$operationId} tidak memiliki application/json");
        }

        return $json->schema;
    }

    private function operation(string $operationId): object
    {
        foreach ($this->doc->paths as $item) {
            foreach ($item as $operation) {
                if ($operation->operationId === $operationId) {
                    return $operation;
                }
            }
        }

        throw new RuntimeException("operationId tidak dikenal: {$operationId}");
    }

    private function resolve(string $ref): object
    {
        $node = $this->doc;
        foreach (explode('/', ltrim($ref, '#/')) as $part) {
            $node = $node->{$part};
        }

        return $node;
    }
}
