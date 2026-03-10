window.pickAndExtractPdfText = async function () {
    return new Promise((resolve, reject) => {
        // Create an invisible file input element
        let input = document.createElement('input');
        input.type = 'file';
        input.accept = '.pdf';
        input.style.display = 'none';

        // Listen for file selection
        input.onchange = async (e) => {
            let file = e.target.files[0];
            if (!file) {
                reject('No file selected');
                return;
            }

            try {
                // Read file directly into JS memory, bypassing Flutter WASM heap
                let arrayBuffer = await file.arrayBuffer();
                let bytes = new Uint8Array(arrayBuffer);

                // Pass bytes to Mozilla's PDF.js
                const loadingTask = pdfjsLib.getDocument({ data: bytes });
                const pdf = await loadingTask.promise;
                let maxPages = pdf.numPages;
                let extractedText = '';

                for (let pageNo = 1; pageNo <= maxPages; pageNo++) {
                    const page = await pdf.getPage(pageNo);
                    const textContent = await page.getTextContent();

                    // Join the text items with spaces
                    const pageText = textContent.items.map(item => item.str).join(' ');
                    extractedText += pageText + ' \n';
                }

                // Return both filename and text encoded as JSON string
                const resultString = JSON.stringify({
                    name: file.name,
                    text: extractedText
                });

                resolve(resultString);

            } catch (err) {
                console.error('Error extracting PDF text via pdf.js:', err);
                reject(err.toString());
            }

            // Clean up the DOM node
            document.body.removeChild(input);
        };

        // Handle user cancelling the file picker dialogue (mostly works depending on browser)
        input.oncancel = () => {
            document.body.removeChild(input);
            reject('User cancelled file selection');
        };

        // Add to DOM and click it to open the picker dialog
        document.body.appendChild(input);
        input.click();
    });
};
