<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Gemini;
use Gemini\Data\Blob;
use Gemini\Enums\MimeType;
// 1. IMPORTANTE: Importamos la clase de configuración de datos de Gemini
use Gemini\Data\GenerationConfig;
use Gemini\Enums\ResponseMimeType;
use Illuminate\Http\Request;

class DocExtractController extends Controller
{
    public function extract(Request $request)
    {
        // Validamos que el archivo sea estrictamente un PDF
        $request->validate([
            'document' => 'required|file|mimes:pdf|max:10240',
        ]);

        $file = $request->file('document');
        $fileData = base64_encode(file_get_contents($file->getRealPath()));

        // 1. Inicializar el cliente usando el Facade o la clase estática de la librería
        $client = Gemini::client(config('services.gemini.key'));

        $schema = [
            'type' => 'ARRAY',
            'items' => [
                'type' => 'OBJECT',
                'properties' => [
                    'announcementNumber' => [
                        'type' => 'STRING',
                        'description' => 'The ad number e.g. 12345678',
                    ],
                    'date' => [
                        'type' => 'STRING',
                        'description' => 'The date of the ad e.g. 01.01.2024',
                        'format' => 'date-time',
                    ],
                    'periodAssignment' => [
                        'type' => 'ARRAY',
                        'description' => 'Start date and end date of the announcement',
                        'items' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'startDate' => [
                                    'type' => 'STRING',
                                    'format' => 'date-time',
                                ],
                                'endDate' => [
                                    'type' => 'STRING',
                                    'format' => 'date-time',
                                ],
                            ],
                        ],
                    ],
                    'days' => [
                        'type' => 'NUMBER',
                        'description' => 'Total days announced',
                    ],
                ],
                'propertyOrdering' => ['announcementNumber', 'date', 'periodAssignment', 'days'],
            ],
        ];

        try {
            // 1. Usamos 'generativeModel' pasándole explícitamente el string de 'gemini-2.5-flash'
            // 2. Construimos el objeto de configuración estructurado usando la clase correcta del SDK
            // 2. Crea el objeto GenerationConfig
            $generationConfig = new GenerationConfig(
                responseMimeType: ResponseMimeType::APPLICATION_JSON,
                responseSchema: $schema
            );
            // 3. Usamos MimeType::APPLICATION_PDF para solucionar la advertencia del editor
            $response = $client->generativeModel(model: 'gemini-2.5-flash')
            ->withGenerationConfig($generationConfig)
            ->generateContent('Extract the structured data from the following PDF file',
                new Blob(
                    mimeType: MimeType::APPLICATION_PDF,
                    data: $fileData
                )
            );

            $extractedData = json_decode($response->text(), true);

            return response()->json([
                'success' => true,
                'data' => $extractedData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al extraer datos: ' . $e->getMessage()
            ], 500);
        }
    }
}
