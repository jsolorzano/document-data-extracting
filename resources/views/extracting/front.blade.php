@extends('layouts.app')

@section('title', 'Extracting')

@section('content')
    <div class="bg-white p-6 rounded shadow" x-data="dataExtracting()">
        <h1 class="text-2xl font-bold mb-4">Extracción de datos</h1>

        <!-- Probando Alpine dentro de la vista hija -->
        <div x-data="{ count: 0 }" class="p-4 border border-dashed rounded bg-gray-50 text-center">
            <p class="mb-2 text-gray-700">Imagen o archivo del documento:</p>
            <label for="doc"
                class="flex flex-col items-center justify-center w-full h-40 border-2 border-gray-300 border-dashed rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100 transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10 text-gray-500 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1M12 12v9m0-9l3 3m-3-3l-3 3"/>
                </svg>
                <div class="text-center">
                    <p class="text-center text-gray-800 font-medium">
                    Seleccione un documento
                    </p>
                </div>
            </label>
            <input class="hidden block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 dark:text-gray-400 focus:outline-none"
            aria-describedby="doc_help" id="doc" name="doc" type="file"
            @change="previewImageDom($event.target)" accept="application/pdf,image/jpeg,image/jpg,image/png,image/webp">
            <div class="mt-1 text-sm text-gray-500" id="doc_help">Por favor, asegúrese de que la foto sea nítida y que incluya ambas caras del documento si aplica.</div>
        </div>

        <div class="mt-3">
            <ul>
                <li>
                    <span>Número de documento:</span>
                    <span x-text="doc_number"></span>
                </li>
                <li>
                    <span>Emisión del documento:</span>
                    <span x-text="doc_issue"></span></li>
                <li>
                    <span>Expiración del documento:</span>
                    <span x-text="doc_expires"></span></li>
                <li>
                    <span>Días del documento:</span>
                    <span x-text="doc_days"></span>
                </li>
            </ul>
        </div>

        <div class="mt-3">
            <span>Mensaje de alerta: </span><span x-text="docAlert"></span>
        </div>
    </div>
    <script>
        function dataExtracting() {
            return {
                async init() {
                    console.log("Iniciando formulario de extracción...");
                },

                doc_number: '',
                doc_issue: '',
                doc_expires: '',
                doc_days: '',
                docAlert: '',

                validateType(target){
                    let result = true;
                    let matchText = "application/pdf|image/jpeg|image/jpg|image/png|image/webp";
                    let isValid = target.files[0].type.match(matchText);

                    if(isValid){
                        result = true;
                    }else{
                        this.docAlert = '{{__("Invalid file: You must upload a file in a supported format (jpeg, png, jpg, webp)")}}';
                        result = false;
                    }
                    return result;
                },

                previewImageDom(target){
                    if(target.files.length > 0){
                        let validateFile = this.validateType(target);
                        if(validateFile){
                            this.readDocument(target.files[0]);
                        }
                    }else{
                        this.docAlert = '';
                    }
                },

                base64Doc: null,

                readDocument(file){
                    // Get base64 data
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        this.base64Doc = e.target.result;
                        //console.log('base64Data: ', this.base64Doc);
                        this.verifyDataWithGemini(this.base64Doc);
                    };
                    reader.readAsDataURL(file);
                    // End Get base64 data
                },

                /**
                 * Extract the required data from the document using the Gemini API.
                 */
                async verifyDataWithGemini(base64Doc){
                    //console.log('Consultando a Gemini...');

                    const ai = new GeminiAI({ apiKey: "{{config('services.gemini.key')}}" });

                    const contents = [
                        { text: "Extract the structured data from the following PDF file" },
                        {
                            inlineData: {
                                mimeType: 'application/pdf',
                                data: base64Doc.split('data:application/pdf;base64,')[1]
                            }
                        }
                    ];

                    const config = {
                        responseMimeType: "application/json",
                        responseSchema: {
                            type: Type.ARRAY,
                            items: {
                                type: Type.OBJECT,
                                properties: {
                                    announcementNumber: {
                                        type: Type.STRING,
                                        description: "The ad number e.g. 12345678"
                                    },
                                    date: {
                                        type: Type.STRING,
                                        description: "The date of the ad e.g. 01.01.2024",
                                        format: "date-time"
                                    },
                                    periodAssignment: {
                                        type: Type.ARRAY,
                                        description: "Start date and end date of the announcement",
                                        items: {
                                            type: Type.OBJECT,
                                            properties: {
                                                startDate: {
                                                    type: Type.STRING,
                                                    format: "date-time"
                                                },
                                                endDate: {
                                                    type: Type.STRING,
                                                    format: "date-time"
                                                }
                                            }
                                        }
                                    },
                                    days: {
                                        type: Type.FLOAT,
                                        description: "Total days announced"
                                    }
                                },
                                propertyOrdering: ["announcementNumber", "date", "periodAssignment", "days"],
                            },
                        }
                    }

                    const response = await ai.models.generateContent({
                        model: "gemini-2.5-flash",
                        contents: contents,
                        config: config
                    });
                    result = JSON.parse(response.text);
                    /* console.log(result);
                    console.log(typeof result);
                    console.log(Array.isArray(result));
                    console.log(result instanceof Object); */

                    if(result.length == 0){
                        this.docAlert = 'No se encontraron los datos requeridos';
                    }else{
                        this.doc_number = result[0].announcementNumber;
                        this.doc_issue = result[0].periodAssignment[0].startDate.slice(0, 10);
                        this.doc_expires = result[0].periodAssignment[0].endDate.slice(0, 10);
                        this.doc_days = result[0].days;
                    }
                },
            }
        }
    </script>
@endsection