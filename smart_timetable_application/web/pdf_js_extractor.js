window.pickAndExtractAndAnalyzePdf = async function (geminiApiKey, moduleCode) {
    return new Promise((resolve, reject) => {
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = '.pdf';
        input.style.display = 'none';

        input.onchange = async (e) => {
            const file = e.target.files[0];
            if (!file) {
                reject('No file selected');
                return;
            }

            // Clean up DOM immediately
            if (document.body.contains(input)) {
                document.body.removeChild(input);
            }

            try {
                // ── STEP 1: Read file into JS ArrayBuffer (never touches WASM heap) ──
                const arrayBuffer = await file.arrayBuffer();

                // ── STEP 2: Extract text page-by-page with immediate cleanup ──
                const loadingTask = pdfjsLib.getDocument({ data: arrayBuffer });
                const pdf = await loadingTask.promise;
                const numPages = pdf.numPages;
                let fullText = '';

                for (let pageNo = 1; pageNo <= numPages; pageNo++) {
                    const page = await pdf.getPage(pageNo);
                    const textContent = await page.getTextContent();
                    const pageText = textContent.items.map(item => item.str).join(' ');
                    fullText += pageText + '\n';
                    page.cleanup(); // ← critical: frees decoded bitmap/canvas memory immediately
                }

                // ── STEP 3: Send text directly to Gemini from JS (never crosses WASM bridge) ──
                const prompt = `You are an academic assistant. Extract all important dates, deadlines, tests, exams, assignments, and submission dates from this university module outline/syllabus.

Module code: ${moduleCode}

Return ONLY a valid JSON array. No markdown, no explanation, no code fences. Just raw JSON like this:
[{"title":"Assignment 1 Due","date":"2025-03-15","description":"Submit via portal","type":"assignment"},...]

Types must be one of: assignment, test, exam, project, lecture, other

Syllabus text:
${fullText}`;

                const geminiResponse = await fetch(
                    `https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=${geminiApiKey}`,
                    {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            contents: [{ parts: [{ text: prompt }] }],
                            generationConfig: {
                                temperature: 0.1,
                                maxOutputTokens: 2048,
                            }
                        })
                    }
                );

                if (!geminiResponse.ok) {
                    const errBody = await geminiResponse.text();
                    reject(`Gemini API error ${geminiResponse.status}: ${errBody}`);
                    return;
                }

                const geminiData = await geminiResponse.json();
                const rawResult = geminiData?.candidates?.[0]?.content?.parts?.[0]?.text;

                if (!rawResult) {
                    reject('Gemini returned an empty response.');
                    return;
                }

                // ── STEP 4: Return only the small result + filename to Dart ──
                // This is the ONLY thing that crosses the WASM bridge: ~2KB max
                const finalPayload = JSON.stringify({
                    name: file.name,
                    events: rawResult  // raw string, Dart will parse it
                });

                resolve(finalPayload);

            } catch (err) {
                console.error('[pdf_js_extractor] Error:', err);
                reject(err.toString());
            }
        };

        input.oncancel = () => {
            if (document.body.contains(input)) {
                document.body.removeChild(input);
            }
            reject('User cancelled file selection');
        };

        document.body.appendChild(input);
        input.click();
    });
};
