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
        try {
            //dd($request);

            /*// Captura directamente la superglobal de PHP
            if (isset($_FILES['document'])) {
                $errorCode = $_FILES['document']['error'];
                
                // Mapeo de errores nativos de PHP
                $errors = [
                    0 => 'UPLOAD_ERR_OK (Sin error)',
                    1 => 'UPLOAD_ERR_INI_SIZE (Excede upload_max_filesize en php.ini)',
                    2 => 'UPLOAD_ERR_FORM_SIZE (Excede MAX_FILE_SIZE del formulario HTML)',
                    3 => 'UPLOAD_ERR_PARTIAL (El archivo se subió parcialmente)',
                    4 => 'UPLOAD_ERR_NO_FILE (No se subió ningún archivo)',
                    6 => 'UPLOAD_ERR_NO_TMP_DIR (Falta la carpeta temporal en php.ini)',
                    7 => 'UPLOAD_ERR_CANT_WRITE (Error al escribir el archivo en el disco)',
                    8 => 'UPLOAD_ERR_EXTENSION (Una extensión de PHP detuvo la subida)',
                ];

                return response()->json([
                    'php_raw_error_code' => $errorCode,
                    'description' => $errors[$errorCode] ?? 'Error desconocido',
                    'file_name' => $_FILES['document']['name'],
                    'file_size_bytes' => $_FILES['document']['size'],
                ], 400);
            }

            return response()->json(['message' => 'PHP ni siquiera recibió $_FILES'], 400);*/

            $upload_max_filesize = ini_parse_quantity(ini_get('upload_max_filesize')) / 1024;

            // Validamos que el archivo sea estrictamente un PDF
            $this->validate($request, [
                'document' => [
                    'required',
                    'file',
                    'mimetypes:application/pdf,application/x-pdf,application/acrobat,applications/vnd.pdf,text/pdf,text/x-pdf,image/jpeg,image/png,image/webp',
                    'max:'.$upload_max_filesize
                ]
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
            $message = $e->getMessage();
            $code = 500;

            if ($e instanceof \Illuminate\Validation\ValidationException) {
                $code = 422;
            } elseif ($e instanceof \Illuminate\Http\Exceptions\PostTooLargeException) {
                $message = "El archivo supera el límite de tamaño permitido (".ini_get('upload_max_filesize').").";
                $code = 413;
            } else {
                if (stristr($message, 'RESOURCE_EXHAUSTED') || stristr($message, '429')
                    || stristr($message, 'You exceeded your current quota') || stristr($message, 'Quota exceeded')) {
                    $message = 'Se ha alcanzado el límite de peticiones de la API. Por favor, reintenta en un momento (1 - 2 minutos).';
                }
                $message = 'Error al extraer datos: ' . $message;
            }

            return response()->json([
                'success' => false,
                'message' => $message
            ], $code);
        }
    }
}
