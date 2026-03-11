window.pickAndExtractAndAnalyzePdf = async function (geminiApiKey, moduleCode) {
    return new Promise((resolve, reject) => {
        if (!geminiApiKey || !geminiApiKey.trim()) {
            reject('GEMINI_API_KEY is missing.');
            return;
        }
        if (typeof pdfjsLib === 'undefined' || !pdfjsLib.getDocument) {
            reject('PDF.js failed to load. Please refresh and try again.');
            return;
        }

        const input = document.createElement('input');
        input.type = 'file';
        input.accept = '.pdf';
        input.style.display = 'none';

        input.onchange = async (e) => {
            try {
                const files = input.files || (e && e.target ? e.target.files : null);
                const file = files && files.length > 0 ? files[0] : null;
                if (!file) {
                    reject('No file selected');
                    return;
                }

                if (document.body.contains(input)) {
                    document.body.removeChild(input);
                }

                // ── STEP 1: Read file into JS ArrayBuffer ──
                const arrayBuffer = await file.arrayBuffer();

                // ── STEP 2: Extract text page-by-page ──
                const loadingTask = pdfjsLib.getDocument({ data: arrayBuffer });
                const pdf = await loadingTask.promise;
                const numPages = pdf.numPages;
                let fullText = '';

                for (let pageNo = 1; pageNo <= numPages; pageNo++) {
                    const page = await pdf.getPage(pageNo);
                    const textContent = await page.getTextContent();
                    const pageText = textContent.items.map(item => item.str).join(' ');
                    fullText += pageText + '\n';
                    page.cleanup();
                }

                // ── STEP 3: Send text to Gemini from JS ──
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
                            generationConfig: { temperature: 0.1, maxOutputTokens: 2048 }
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

                // ── STEP 4: Return result + filename to Dart ──
                resolve(JSON.stringify({ name: file.name, events: rawResult }));

            } catch (err) {
                if (document.body.contains(input)) {
                    document.body.removeChild(input);
                }
                console.error('[pdf_js_extractor] Error:', err);
                reject(err && err.message ? err.message : String(err));
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
