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
use PhpOffice\PhpPresentation\IOFactory;

class DocExtractController extends Controller
{
    public function extract(Request $request)
    {
        try {
            // 1. Verificación previa de errores de subida nativos de PHP (antes del validate)
            if (isset($_FILES['document']) && $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
                $errorCode = $_FILES['document']['error'];
                
                if ($errorCode === UPLOAD_ERR_INI_SIZE || $errorCode === UPLOAD_ERR_FORM_SIZE) {
                    return response()->json([
                        'success' => false,
                        'message' => 'El archivo supera el límite de tamaño permitido por el servidor (' . ini_get('upload_max_filesize') . ').'
                    ], 413); // Payload Too Large
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Error al subir el archivo al servidor (Código PHP: ' . $errorCode . ').'
                ], 400);
            }

            $upload_max_filesize = ini_parse_quantity(ini_get('upload_max_filesize')) / 1024;

            // Validamos el documento
            $this->validate($request, [
                'document' => [
                    'required',
                    'file',
                    'mimetypes:application/pdf,application/x-pdf,application/acrobat,applications/vnd.pdf,text/pdf,text/x-pdf,image/jpeg,image/png,image/webp,application/vnd.openxmlformats-officedocument.presentationml.presentation,application/vnd.ms-powerpoint',
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

            //dd($mimeTypeString);

            $pptxMime = 'application/vnd.openxmlformats-officedocument.presentationml.presentation';
            $pptMime  = 'application/vnd.ms-powerpoint';

            // Mapeamos dinámicamente el tipo MIME según el archivo subido
            $geminiMimeType = match ($mimeTypeString) {
                'application/pdf' => MimeType::APPLICATION_PDF,
                'image/jpeg' => MimeType::IMAGE_JPEG,
                'image/png' => MimeType::IMAGE_PNG,
                'image/webp' => MimeType::IMAGE_WEBP,
                // Agregamos el soporte para PowerPoint directamente con su MIME String
                $pptxMime => $pptxMime,
                $pptMime => $pptMime,
                default => throw new \InvalidArgumentException("Tipo de archivo no soportado: {$mimeTypeString}"),
            };

            // Inicializar el cliente usando la API de Gemini
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
            $generationConfig = new GenerationConfig(
                responseMimeType: ResponseMimeType::APPLICATION_JSON,
                responseSchema: $schema
            );
            // Usamos 'generativeModel' pasándole explícitamente el string de 'gemini-2.5-flash'
            if ($mimeTypeString === 'application/vnd.openxmlformats-officedocument.presentationml.presentation') {
                $pptReader = IOFactory::createReader('PowerPoint2007');
                $phpPresentation = $pptReader->load($file->getRealPath());
                $extractedText = '';

                foreach ($phpPresentation->getAllSlides() as $slide) {
                    foreach ($slide->getShapeCollection() as $shape) {
                        if (method_exists($shape, 'getText')) {
                            $extractedText .= $shape->getText() . "\n";
                        }
                    }
                }

                // En lugar de enviar un Blob, envías el texto en el prompt
                $response = $client->generativeModel(model: 'gemini-2.5-flash')
                    ->withGenerationConfig($generationConfig)
                    ->generateContent(
                        "Extract the structured data from the following presentation text:\n\n" . $extractedText
                    );
            }else{
                // Prompt neutral para PDFs o Imágenes
                $response = $client->generativeModel(model: 'gemini-2.5-flash')
                ->withGenerationConfig($generationConfig)
                ->generateContent(
                    'Extract the structured data from the provided document image or file.',
                    new Blob(
                        mimeType: $geminiMimeType, // Tipo MIME dinámico
                        data: $fileData
                    )
                );
            }

            $extractedData = json_decode($response->text(), true);

            return response()->json([
                'success' => true,
                'data' => $extractedData
            ]);

        } catch (\Throwable $e) {
            $message = $e->getMessage();
            $code = 500;

            if ($e instanceof \Illuminate\Validation\ValidationException) {
                $code = 422;
                // Obtenemos el primer mensaje de error formateado de Laravel
                $firstError = collect($e->errors())->flatten()->first();
                $message = $firstError ?? 'Los datos proporcionados no son válidos.';
            } elseif ($e instanceof \Illuminate\Http\Exceptions\PostTooLargeException) {
                $message = "La petición supera el límite de tamaño permitido.";
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
