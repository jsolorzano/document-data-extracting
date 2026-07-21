<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Gemini;
use Gemini\Data\Blob;
use Gemini\Data\DataFormat;
use Gemini\Data\GenerationConfig;
use Gemini\Data\Schema; // Importante para construir el esquema
use Gemini\Enums\DataType; // Importante para los tipos de datos
use Gemini\Enums\MimeType;
use Gemini\Enums\ResponseMimeType;
use Illuminate\Http\Request;

class DocExtractController extends Controller
{
    public function extract(Request $request)
    {
        // Validamos que el archivo sea estrictamente un PDF
        $request->validate([
            'document' => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:10240'
        ]);

        $file = $request->file('document');

        if (!$file || !$file->isValid()) {
            return response()->json([
                'success' => false,
                'message' => 'El archivo no subió correctamente o está dañado.'
            ], 400);
        }

        // Asegurarse de que realmente es un archivo y no un directorio
        if (is_dir($file->getRealPath())) {
            return response()->json([
                'success' => false,
                'message' => 'Se ha recibido un directorio en lugar de un archivo.'
            ], 400);
        }

        $fileData = base64_encode($file->get());
        $mimeTypeString = $file->getMimeType();

        // 2. Mapeamos dinámicamente el tipo MIME según el archivo subido
        $geminiMimeType = match ($mimeTypeString) {
            'application/pdf' => MimeType::APPLICATION_PDF,
            'image/jpeg' => MimeType::IMAGE_JPEG,
            'image/png' => MimeType::IMAGE_PNG,
            'image/webp' => MimeType::IMAGE_WEBP,
            default => throw new \InvalidArgumentException("Tipo de archivo no soportado: {$mimeTypeString}"),
        };

        // 1. Inicializar el cliente usando el Facade o la clase estática de la librería
        $client = Gemini::client(config('services.gemini.key'));

        // Definición del esquema estructurado usando las clases nativas del SDK
        $schema = new Schema(
            type: DataType::ARRAY,
            items: new Schema(
                type: DataType::OBJECT,
                properties: [
                    'announcementNumber' => new Schema(
                        type: DataType::STRING,
                        description: 'The ad number e.g. 12345678'
                    ),
                    'date' => new Schema(
                        type: DataType::STRING,
                        description: 'The date of the ad e.g. 01.01.2024',
                        format: DataFormat::DATETIME
                    ),
                    'periodAssignment' => new Schema(
                        type: DataType::ARRAY,
                        description: 'Start date and end date of the announcement',
                        items: new Schema(
                            type: DataType::OBJECT,
                            properties: [
                                'startDate' => new Schema(
                                    type: DataType::STRING,
                                    format: DataFormat::DATETIME
                                ),
                                'endDate' => new Schema(
                                    type: DataType::STRING,
                                    format: DataFormat::DATETIME
                                ),
                            ]
                        )
                    ),
                    'days' => new Schema(
                        type: DataType::NUMBER,
                        description: 'Total days announced'
                    ),
                ],
                propertyOrdering: ['announcementNumber', 'date', 'periodAssignment', 'days']
            )
        );

        try {
            // 1. Construimos el objeto de configuración estructurado usando la clase correcta del SDK
            // 2. Usamos 'generativeModel' pasándole explícitamente el string de 'gemini-2.5-flash'
            $generationConfig = new GenerationConfig(
                responseMimeType: ResponseMimeType::APPLICATION_JSON,
                responseSchema: $schema
            );
            // 3. Prompt neutral para PDFs o Imágenes
            $response = $client->generativeModel(model: 'gemini-2.5-flash')
            ->withGenerationConfig($generationConfig)
            ->generateContent('Extract the structured data from the provided document image or file.',
                new Blob(
                    mimeType: $geminiMimeType, // Tipo MIME dinámico
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
