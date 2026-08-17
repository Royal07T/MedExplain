<?php

namespace App\Enums;

enum ExtractionMethod: string
{
    case PdfText = 'pdf_text';
    case Ocr = 'ocr';
    case None = 'none';
}