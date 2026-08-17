<?php

namespace App\Enums;

enum AnalysisItemCategory: string
{
    case Fact = 'fact';
    case ReferenceComparison = 'reference_comparison';
    case Education = 'education';
    case PossibleContext = 'possible_context';
    case QuestionForProfessional = 'question_for_professional';
}