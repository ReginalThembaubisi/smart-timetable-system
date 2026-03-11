window.pickAndExtractAndAnalyzePdf = async function (geminiApiKey, moduleCode) {
    // #region agent log H-A H-C: function entry
    fetch('http://127.0.0.1:7754/ingest/eac62228-e5a5-4218-8e10-d3b95ed34d1c',{method:'POST',headers:{'Content-Type':'application/json','X-Debug-Session-Id':'36d0ab'},body:JSON.stringify({sessionId:'36d0ab',hypothesisId:'H-A,H-C',location:'pdf_js_extractor.js:1',message:'pickAndExtractAndAnalyzePdf called',data:{hasApiKey:!!(geminiApiKey&&geminiApiKey.trim()),moduleCode:moduleCode,pdfjsLibDefined:typeof pdfjsLib!=='undefined',pdfjsGetDocDefined:typeof pdfjsLib!=='undefined'&&!!pdfjsLib.getDocument},timestamp:Date.now()})}).catch(()=>{});
    // #endregion

    return new Promise((resolve, reject) => {
        if (!geminiApiKey || !geminiApiKey.trim()) {
            // #region agent log H-C: missing key path
            fetch('http://127.0.0.1:7754/ingest/eac62228-e5a5-4218-8e10-d3b95ed34d1c',{method:'POST',headers:{'Content-Type':'application/json','X-Debug-Session-Id':'36d0ab'},body:JSON.stringify({sessionId:'36d0ab',hypothesisId:'H-C',location:'pdf_js_extractor.js:missing-key',message:'REJECTED: missing API key',timestamp:Date.now()})}).catch(()=>{});
            // #endregion
            reject('GEMINI_API_KEY is missing.');
            return;
        }
        if (typeof pdfjsLib === 'undefined' || !pdfjsLib.getDocument) {
            // #region agent log H-A: pdfjsLib missing path
            fetch('http://127.0.0.1:7754/ingest/eac62228-e5a5-4218-8e10-d3b95ed34d1c',{method:'POST',headers:{'Content-Type':'application/json','X-Debug-Session-Id':'36d0ab'},body:JSON.stringify({sessionId:'36d0ab',hypothesisId:'H-A',location:'pdf_js_extractor.js:pdfjsLib-check',message:'REJECTED: pdfjsLib undefined',timestamp:Date.now()})}).catch(()=>{});
            // #endregion
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

                // #region agent log H-B: file selected check
                fetch('http://127.0.0.1:7754/ingest/eac62228-e5a5-4218-8e10-d3b95ed34d1c',{method:'POST',headers:{'Content-Type':'application/json','X-Debug-Session-Id':'36d0ab'},body:JSON.stringify({sessionId:'36d0ab',hypothesisId:'H-B',location:'pdf_js_extractor.js:onchange',message:'onchange fired',data:{fileFound:!!file,fileName:file?file.name:null,fileSize:file?file.size:null,inputFilesLen:input.files?input.files.length:null,eTargetFilesLen:(e&&e.target&&e.target.files)?e.target.files.length:null},timestamp:Date.now()})}).catch(()=>{});
                // #endregion

                if (!file) {
                    reject('No file selected');
                    return;
                }

                // Clean up DOM immediately
                if (document.body.contains(input)) {
                    document.body.removeChild(input);
                }

                // ── STEP 1: Read file into JS ArrayBuffer (never touches WASM heap) ──
                const arrayBuffer = await file.arrayBuffer();

                // ── STEP 2: Extract text page-by-page with immediate cleanup ──
                // #region agent log H-A: before pdfjsLib.getDocument
                fetch('http://127.0.0.1:7754/ingest/eac62228-e5a5-4218-8e10-d3b95ed34d1c',{method:'POST',headers:{'Content-Type':'application/json','X-Debug-Session-Id':'36d0ab'},body:JSON.stringify({sessionId:'36d0ab',hypothesisId:'H-A',location:'pdf_js_extractor.js:before-getDocument',message:'calling pdfjsLib.getDocument',data:{arrayBufferByteLength:arrayBuffer.byteLength},timestamp:Date.now()})}).catch(()=>{});
                // #endregion
                const loadingTask = pdfjsLib.getDocument({ data: arrayBuffer });
                const pdf = await loadingTask.promise;
                const numPages = pdf.numPages;
                let fullText = '';

                // #region agent log H-A: pdf loaded
                fetch('http://127.0.0.1:7754/ingest/eac62228-e5a5-4218-8e10-d3b95ed34d1c',{method:'POST',headers:{'Content-Type':'application/json','X-Debug-Session-Id':'36d0ab'},body:JSON.stringify({sessionId:'36d0ab',hypothesisId:'H-A',location:'pdf_js_extractor.js:pdf-loaded',message:'PDF loaded, extracting pages',data:{numPages:numPages},timestamp:Date.now()})}).catch(()=>{});
                // #endregion

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

                // #region agent log H-C H-D: before Gemini call
                fetch('http://127.0.0.1:7754/ingest/eac62228-e5a5-4218-8e10-d3b95ed34d1c',{method:'POST',headers:{'Content-Type':'application/json','X-Debug-Session-Id':'36d0ab'},body:JSON.stringify({sessionId:'36d0ab',hypothesisId:'H-C,H-D',location:'pdf_js_extractor.js:before-gemini',message:'calling Gemini API',data:{promptLength:prompt.length,textLength:fullText.length},timestamp:Date.now()})}).catch(()=>{});
                // #endregion

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
                    // #region agent log H-C: gemini HTTP error
                    fetch('http://127.0.0.1:7754/ingest/eac62228-e5a5-4218-8e10-d3b95ed34d1c',{method:'POST',headers:{'Content-Type':'application/json','X-Debug-Session-Id':'36d0ab'},body:JSON.stringify({sessionId:'36d0ab',hypothesisId:'H-C',location:'pdf_js_extractor.js:gemini-error',message:'Gemini HTTP error',data:{status:geminiResponse.status,body:errBody.substring(0,300)},timestamp:Date.now()})}).catch(()=>{});
                    // #endregion
                    reject(`Gemini API error ${geminiResponse.status}: ${errBody}`);
                    return;
                }

                const geminiData = await geminiResponse.json();
                const rawResult = geminiData?.candidates?.[0]?.content?.parts?.[0]?.text;

                // #region agent log H-C H-D: gemini response received
                fetch('http://127.0.0.1:7754/ingest/eac62228-e5a5-4218-8e10-d3b95ed34d1c',{method:'POST',headers:{'Content-Type':'application/json','X-Debug-Session-Id':'36d0ab'},body:JSON.stringify({sessionId:'36d0ab',hypothesisId:'H-C,H-D',location:'pdf_js_extractor.js:gemini-response',message:'Gemini responded',data:{hasResult:!!rawResult,rawResultPreview:rawResult?rawResult.substring(0,200):null},timestamp:Date.now()})}).catch(()=>{});
                // #endregion

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

                // #region agent log H-D: before resolve
                fetch('http://127.0.0.1:7754/ingest/eac62228-e5a5-4218-8e10-d3b95ed34d1c',{method:'POST',headers:{'Content-Type':'application/json','X-Debug-Session-Id':'36d0ab'},body:JSON.stringify({sessionId:'36d0ab',hypothesisId:'H-D',location:'pdf_js_extractor.js:before-resolve',message:'resolving promise',data:{payloadLength:finalPayload.length},timestamp:Date.now()})}).catch(()=>{});
                // #endregion

                resolve(finalPayload);

            } catch (err) {
                if (document.body.contains(input)) {
                    document.body.removeChild(input);
                }
                // #region agent log ALL: catch block
                fetch('http://127.0.0.1:7754/ingest/eac62228-e5a5-4218-8e10-d3b95ed34d1c',{method:'POST',headers:{'Content-Type':'application/json','X-Debug-Session-Id':'36d0ab'},body:JSON.stringify({sessionId:'36d0ab',hypothesisId:'ALL',location:'pdf_js_extractor.js:catch',message:'caught error in onchange',data:{errMessage:err&&err.message?err.message:String(err),errType:err&&err.constructor?err.constructor.name:typeof err,errStack:err&&err.stack?err.stack.substring(0,400):null},timestamp:Date.now()})}).catch(()=>{});
                // #endregion
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
