<?php
require_once __DIR__ . '/../includes/api_helpers.php';

setCORSHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSONResponse(false, null, 'Method not allowed', 405);
}

try {
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No file uploaded or upload error occurred.');
    }

    $fileTmpPath = $_FILES['file']['tmp_name'];
    $fileName = $_FILES['file']['name'];
    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    if ($fileExtension !== 'docx') {
        throw new Exception('Unsupported file type. Please upload a DOCX file.');
    }

    // Function to extract text from a DOCX file (which is a ZIP)
    function readDocxText($filename)
    {
        $text = '';
        $zip = new ZipArchive();

        if ($zip->open($filename) === true) {
            // Read document.xml which contains the text
            if (($index = $zip->locateName('word/document.xml')) !== false) {
                $data = $zip->getFromIndex($index);
                $zip->close();

                // Load XML and strip tags
                $dom = new DOMDocument();
                $dom->loadXML($data, LIBXML_NOENT | LIBXML_XINCLUDE | LIBXML_NOERROR | LIBXML_NOWARNING);
                $text = strip_tags($dom->saveXML());

                // Clean up whitespace
                $text = preg_replace('/\s+/', ' ', $text);
                return trim($text);
            }
            $zip->close();
        }
        return '';
    }

    $extractedText = readDocxText($fileTmpPath);

    if (empty($extractedText)) {
        throw new Exception('Could not extract any text from the document.');
    }

    sendJSONResponse(true, ['text' => $extractedText], 'Text extracted successfully');

} catch (Exception $e) {
    handleAPIError($e, 'Failed to extract text from document');
}
?>