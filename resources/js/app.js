import './bootstrap';

import { GoogleGenAI, Type } from "@google/genai";
import Alpine from 'alpinejs';

window.GeminiAI = GoogleGenAI;
window.Type = Type;
window.Alpine = Alpine;

Alpine.start();