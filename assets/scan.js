document.addEventListener("DOMContentLoaded", function () {
    const scanButton = document.getElementById("pantheon-tls-scan");
    const statusDiv = document.getElementById("pantheon-tls-scan-status");
    const resultsCard = document.querySelector(".failing-urls pre.card");
    const alertContainer = document.getElementById("pantheon-tls-alert-container");

    if (!scanButton) return;

    let totalPassing = 0;
    let totalFailing = 0;
    let allFailingUrls = [];

    scanButton.addEventListener("click", function () {
        scanButton.disabled = true;
        scanButton.textContent = "Scanning...";

        statusDiv.innerHTML = '<p>Scanning site for TLS compatibility...</p>';
        alertContainer.innerHTML = ''; // Clear previous notices

        // Create progress bar with percentage display
        const progressBarContainer = document.createElement("div");
        progressBarContainer.className = "tls-progress-bar";
        progressBarContainer.innerHTML = `
            <div class="tls-progress"></div>
            <span class="tls-progress-text">0%</span>
        `;
        statusDiv.appendChild(progressBarContainer);

        const progressText = document.querySelector(".tls-progress-text");

        function scanBatch(offset) {
            fetch(tlsCheckerAjax.ajax_url, {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded"
                },
                body: new URLSearchParams({
                    action: "pantheon_tls_checker_scan",
                    nonce: tlsCheckerAjax.nonce,
                    offset: offset
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    totalPassing += data.data.passing;
                    totalFailing += data.data.failing;
                    allFailingUrls = [...new Set([...allFailingUrls, ...data.data.failing_urls])];

                    let progress = Math.round((data.data.progress / data.data.total) * 100);
                    document.querySelector(".tls-progress").style.width = progress + "%";
                    progressText.textContent = `${progress}%`;

                    // Change text color dynamically based on progress
                    if (progress < 50) {
                        progressText.style.color = "#333"; // Dark text for readability
                    } else {
                        progressText.style.color = "#fff"; // Light text when background is filled
                    }

                    if (data.data.remaining > 0) {
                        scanBatch(offset + data.data.batch_size);
                    } else {
                        // Update results
                        if (resultsCard) {
                            resultsCard.textContent = allFailingUrls.length > 0
                                ? allFailingUrls.join("\n")
                                : "No failing URLs detected.";
                        }

                        // Show WP-style admin notice
                        alertContainer.innerHTML = `
                            <div class="notice notice-info is-dismissible">
                                <p><strong>Scan complete.</strong><br />
                                <p><strong>Passing URLs:</strong> ${totalPassing}<br />
                                <p><strong>Failing URLs:</strong> ${totalFailing}</p>
                            </div>
                        `;

                        document.querySelectorAll(".is-dismissible").forEach(function (el) {
                            el.addEventListener("click", function () {
                                el.style.display = "none";
                            });
                        });

                        statusDiv.innerHTML = '';
                        scanButton.disabled = false;
                        scanButton.textContent = "Scan site for TLS 1.2/1.3 compatibility";
                    }
                } else {
                    alertContainer.innerHTML = `<div class="notice notice-error"><p>Error: ${data.data}</p></div>`;
                    scanButton.disabled = false;
                    scanButton.textContent = "Scan site for TLS 1.2/1.3 compatibility";
                }
            })
            .catch(error => {
                alertContainer.innerHTML = `<div class="notice notice-error"><p>An error occurred while scanning.</p></div>`;
                scanButton.disabled = false;
                scanButton.textContent = "Scan site for TLS 1.2/1.3 compatibility";
            });
        }

        scanBatch(0);
    });
});
