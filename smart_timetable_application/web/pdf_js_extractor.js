window.pickAndExtractAndAnalyzePdf = async function (geminiApiKey, moduleCode) {
    // #region agent log H-A H-C: function entry
    console.log('[DBG-36d0ab][JS] pickAndExtractAndAnalyzePdf called', {
        hasApiKey: !!(geminiApiKey && geminiApiKey.trim()),
        moduleCode: moduleCode,
        pdfjsLibDefined: typeof pdfjsLib !== 'undefined',
        pdfjsGetDocDefined: typeof pdfjsLib !== 'undefined' && !!pdfjsLib.getDocument
    });
    // #endregion

    return new Promise((resolve, reject) => {
        if (!geminiApiKey || !geminiApiKey.trim()) {
            console.log('[DBG-36d0ab][JS] REJECTED: missing API key');
            reject('GEMINI_API_KEY is missing.');
            return;
        }
        if (typeof pdfjsLib === 'undefined' || !pdfjsLib.getDocument) {
            console.log('[DBG-36d0ab][JS] REJECTED: pdfjsLib undefined/broken');
            reject('PDF extractor failed to load (pdfjsLib missing). Please refresh and try again.');
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

                // #region agent log H-B: file selected
                console.log('[DBG-36d0ab][JS] onchange fired', {
                    fileFound: !!file,
                    fileName: file ? file.name : null,
                    fileSize: file ? file.size : null,
                    inputFilesLen: input.files ? input.files.length : null
                });
                // #endregion

                if (!file) {
                    reject('No file selected');
                    return;
                }

                if (document.body.contains(input)) {
                    document.body.removeChild(input);
                }

                // ── STEP 1: Read file into JS ArrayBuffer ──
                const arrayBuffer = await file.arrayBuffer();

                // #region agent log H-A: before getDocument
                console.log('[DBG-36d0ab][JS] calling pdfjsLib.getDocument', { byteLength: arrayBuffer.byteLength });
                // #endregion

                const loadingTask = pdfjsLib.getDocument({ data: arrayBuffer });
                const pdf = await loadingTask.promise;
                const numPages = pdf.numPages;

                // #region agent log H-A: pdf loaded
                console.log('[DBG-36d0ab][JS] PDF loaded', { numPages: numPages });
                // #endregion

                let fullText = '';
                for (let pageNo = 1; pageNo <= numPages; pageNo++) {
                    const page = await pdf.getPage(pageNo);
                    const textContent = await page.getTextContent();
                    const pageText = textContent.items.map(item => item.str).join(' ');
                    fullText += pageText + '\n';
                    page.cleanup();
                }

                // #region agent log H-C H-D: before Gemini
                console.log('[DBG-36d0ab][JS] calling Gemini', { promptLen: fullText.length });
                // #endregion

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
                    // #region agent log H-C: gemini error
                    console.log('[DBG-36d0ab][JS] Gemini HTTP error', { status: geminiResponse.status, body: errBody.substring(0, 300) });
                    // #endregion
                    reject(`Gemini API error ${geminiResponse.status}: ${errBody}`);
                    return;
                }

                const geminiData = await geminiResponse.json();
                const rawResult = geminiData?.candidates?.[0]?.content?.parts?.[0]?.text;

                // #region agent log H-C H-D: gemini response
                console.log('[DBG-36d0ab][JS] Gemini responded', {
                    hasResult: !!rawResult,
                    preview: rawResult ? rawResult.substring(0, 200) : null
                });
                // #endregion

                if (!rawResult) {
                    reject('Gemini returned an empty response.');
                    return;
                }

                const finalPayload = JSON.stringify({ name: file.name, events: rawResult });

                // #region agent log H-D: resolving
                console.log('[DBG-36d0ab][JS] resolving promise', { payloadLen: finalPayload.length });
                // #endregion

                resolve(finalPayload);

            } catch (err) {
                if (document.body.contains(input)) {
                    document.body.removeChild(input);
                }
                // #region agent log ALL: catch
                console.log('[DBG-36d0ab][JS] CAUGHT ERROR in onchange', {
                    errMessage: err && err.message ? err.message : String(err),
                    errType: err && err.constructor ? err.constructor.name : typeof err,
                    errStack: err && err.stack ? err.stack.substring(0, 500) : null
                });
                // #endregion
                console.error('[pdf_js_extractor] Error:', err);
                reject(err && err.message ? err.message : String(err));
            }
        };

        input.oncancel = () => {
            if (document.body.contains(input)) {
                document.body.removeChild(input);
            }
            console.log('[DBG-36d0ab][JS] user cancelled file selection');
            reject('User cancelled file selection');
        };

        document.body.appendChild(input);
        input.click();
    });
};
