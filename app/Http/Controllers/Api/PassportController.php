<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Response;

class PassportController extends Controller
{

    public function uploadPassport(Request $request)
    {
        $request->validate([
            'passport_image' => 'required|file|mimes:jpeg,png,jpg|max:5120',
        ]);

        $filePath = $request->file('passport_image')->store('passports', 'public');
        $imagePath = url('/storage/' . $filePath);

        $systemPrompt = "You are an expert in passport information extraction. Analyze the provided passport document using both text and visual elements to extract the following fields: " .
        "- first_name " .
        "- last_name " .
        "- passport_number " .
        "- nationality (2-letter country code, e.g., \"PK\" for Pakistan) " .
        "- date_of_birth (YYYY-MM-DD) " .
        "- sex " .
        "- expiry_date (YYYY-MM-DD) " .
        "- passenger_type " .
        "  - Determine from date_of_birth: " .
        "    - \"ADT\" if age > 11 years " .
        "    - \"CNN\" (Child) if age between 2 and 11 years " .
        "    - \"INF\" (Infant) if age < 2 years " .
        "- title " .
        "  - If sex is male, title should be \"MR\" " .
        "  - If sex is female: " .
        "    - If \"husband name\" or \"husband's name\" is mentioned, title should be \"MRS\" " .
        "    - If \"father name\" or \"father's name\" is mentioned, title should be \"MS\" " .
        "Special Handling Rules: " .
        "- If surname (last name) is missing, include full name in last_name field. " .
        "- For husband/father name fields written as \"TAJ, FAQIR\", remove the comma and reverse the order to get \"FAQIR TAJ\". " .
        "- Trim and clean any extra whitespace or punctuation. " .
        "Output: Return only a JSON object with the extracted fields listed above.";

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . env('OPENAI_API_KEY')
        ])->post('https://api.openai.com/v1/responses', [
            'model' => 'gpt-4.1-mini',
            'input' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            "type" => "input_text",
                            "text" => $systemPrompt
                        ],
                        [
                            'type' => 'input_image',
                            'image_url' => $imagePath
                        ]
                    ]
                ]
            ],

        ]);
        $responseData = $response->json();
        $content = $responseData['output'][0]['content'][0]['text'] ?? null;
        unlink(storage_path('app/public/passports/'. basename($imagePath)));
        if (!$content) {
            return Response::errorResponse(500,'No content received from ChatGPT API');
        }

        // Try to extract JSON
        $jsonStart = strpos($content, '{');
        $jsonEnd = strrpos($content, '}');
        $jsonString = substr($content, $jsonStart, $jsonEnd - $jsonStart + 1);
        $passportInfo = json_decode($jsonString, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return Response::errorResponse(500,'Invalid Passport Please Provide Valide Passport');
        }
        return Response::successResponse(200,'Passport information Scaned successfully', $passportInfo);
    }
}
