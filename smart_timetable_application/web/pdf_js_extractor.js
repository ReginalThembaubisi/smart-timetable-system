window.extractPdfTextFromBytes = async function (bytes) {
    try {
        // Load the PDF documents bytes via pdf.js
        const loadingTask = pdfjsLib.getDocument({ data: bytes });
        const pdf = await loadingTask.promise;
        let maxPages = pdf.numPages;
        let extractedText = '';

        for (let pageNo = 1; pageNo <= maxPages; pageNo++) {
            const page = await pdf.getPage(pageNo);
            const textContent = await page.getTextContent();
            const pageText = textContent.items.map(item => item.str).join(' ');
            extractedText += pageText + ' \n';
        }

        return extractedText;
    } catch (error) {
        console.error('Error extracting PDF text via pdf.js:', error);
        throw error;
    }
};
