// tos_modal.js - Terms of Service Modal Display

/**
 * Shows the Terms of Service modal with content from tos.md
 */
function showTosModal() {
    // Fetch tos.md content
    $.ajax({
        url: 'tos.md',
        method: 'GET',
        dataType: 'text',
        success: function(markdown) {
            // Convert markdown to HTML
            const htmlContent = markdownToHtml(markdown);
            
            // Create modal HTML
            const modalHtml = `
                <div class="modal fade" id="tosModal" tabindex="-1" aria-labelledby="tosModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                        <div class="modal-content" style="background: #2a2a2a; color: #e0e0e0; border: 1px solid #444;">
                            <div class="modal-header" style="border-bottom: 1px solid #444;">
                                <h5 class="modal-title" id="tosModalLabel">
                                    <i class="fas fa-gavel"></i> Terms of Service
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="filter: invert(1);"></button>
                            </div>
                            <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                                <div class="tos-content">
                                    ${htmlContent}
                                </div>
                            </div>
                            <div class="modal-footer" style="border-bottom: 1px solid #444;">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                    <i class="fas fa-times"></i> Close
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Remove existing modal if present
            $('#tosModal').remove();
            
            // Add modal to body
            $('body').append(modalHtml);
            
            // Show the modal
            const modal = new bootstrap.Modal(document.getElementById('tosModal'));
            modal.show();
        },
        error: function(xhr, status, error) {
            console.error('Error loading tos.md:', error);
            alert('Error loading Terms of Service. Please try again later.');
        }
    });
}

/**
 * Convert basic markdown to HTML
 * Supports: headers, bold, italic, lists, paragraphs, and line breaks
 */
function markdownToHtml(markdown) {
    let html = markdown;
    
    // Convert headers (##, ###, ####)
    html = html.replace(/^#### (.*$)/gim, '<h4>$1</h4>');
    html = html.replace(/^### (.*$)/gim, '<h3>$1</h3>');
    html = html.replace(/^## (.*$)/gim, '<h2>$1</h2>');
    html = html.replace(/^# (.*$)/gim, '<h1>$1</h1>');
    
    // Convert bold and italic
    html = html.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
    html = html.replace(/\*(.*?)\*/g, '<em>$1</em>');
    
    // Split into lines for processing lists and paragraphs
    const lines = html.split('\n');
    const processed = [];
    let inList = false;
    let listHtml = '';
    
    for (let i = 0; i < lines.length; i++) {
        const line = lines[i].trim();
        
        if (line.startsWith('- ')) {
            // List item
            if (!inList) {
                inList = true;
                listHtml = '<ul style="line-height: 1.6;">';
            }
            listHtml += '<li>' + line.substring(2) + '</li>';
        } else {
            // Close list if we were in one
            if (inList) {
                listHtml += '</ul>';
                processed.push(listHtml);
                listHtml = '';
                inList = false;
            }
            
            // Add non-list content
            if (line === '') {
                processed.push('<br>');
            } else if (!line.startsWith('<h')) {
                processed.push('<p>' + line + '</p>');
            } else {
                processed.push(line);
            }
        }
    }
    
    // Close any open list
    if (inList) {
        listHtml += '</ul>';
        processed.push(listHtml);
    }
    
    html = processed.join('\n');
    
    // Add styling classes
    html = html.replace(/<h2>/g, '<h2 style="color: #4a9eff; margin-top: 20px; margin-bottom: 10px;">');
    html = html.replace(/<h3>/g, '<h3 style="color: #6ab8ff; margin-top: 15px; margin-bottom: 8px;">');
    html = html.replace(/<p>/g, '<p style="color: #bbb; margin-bottom: 10px; line-height: 1.6;">');
    
    return html;
}