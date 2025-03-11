// Function to fetch and display comments with time ago format
function fetchComments(employeeId) {
    fetch('fetch_comments.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ employee_id: employeeId })
    })
    .then(response => response.json())
    .then(data => {
        const commentList = document.querySelector('.modal-body .comment-list');
        commentList.innerHTML = ''; // Clear existing comments

        if (data.comments && data.comments.length > 0) {
            data.comments.forEach(comment => {
                const commentTime = getTimeAgo(comment.comment_time); // Apply the time ago function

                // Create comment element
                const commentElement = document.createElement('div');
                commentElement.classList.add('comment');

                commentElement.innerHTML = `
                    <div class="comment-header">
                        <span class="comment-author">${comment.username || 'Anonymous'}</span>
                        <span class="comment-time">${commentTime}</span>
                    </div>
                    <div class="comment-content">${comment.comment}</div>
                    <div class="comment-actions">
                        <span class="comment-action"><i class="bi bi-hand-thumbs-up"></i> Like</span>
                        <span class="comment-action"><i class="bi bi-reply"></i> Reply</span>
                    </div>
                `;

                commentList.appendChild(commentElement);
            });

            // Update the comment count
            const commentCountElement = document.getElementById(`comment-count-${employeeId}`);
            if (commentCountElement) {
                commentCountElement.textContent = data.total_comments;
            }
        } else {
            // Show empty state
            const emptyState = document.createElement('div');
            emptyState.classList.add('empty-comments');
            emptyState.innerHTML = `
                <i class="bi bi-chat-square-text"></i>
                <p>No comments yet. Be the first to comment!</p>
            `;
            commentList.appendChild(emptyState);

            // Update the comment count to 0
            const commentCountElement = document.getElementById(`comment-count-${employeeId}`);
            if (commentCountElement) {
                commentCountElement.textContent = 0;
            }
        }
    })
    .catch(error => console.error('Error:', error));
}

// Function to calculate time ago
function getTimeAgo(date) {
    const now = new Date();
    const past = new Date(date); // Ensure 'date' is a Date object
    const seconds = Math.floor((now - past) / 1000);
    
    let interval = Math.floor(seconds / 31536000);
    if (interval >= 1) return interval === 1 ? '1 year ago' : interval + ' years ago';

    interval = Math.floor(seconds / 2592000);
    if (interval >= 1) return interval === 1 ? '1 month ago' : interval + ' months ago';

    interval = Math.floor(seconds / 86400);
    if (interval >= 1) return interval === 1 ? '1 day ago' : interval + ' days ago';

    interval = Math.floor(seconds / 3600);
    if (interval >= 1) return interval === 1 ? '1 hour ago' : interval + ' hours ago';

    interval = Math.floor(seconds / 60);
    if (interval >= 1) return interval === 1 ? '1 minute ago' : interval + ' minutes ago';

    return seconds < 10 ? 'just now' : seconds + ' seconds ago';
}


console.log(getTimeAgo("2024-02-22 14:30:00")); // Output: "X hours ago" or similar
console.log(getTimeAgo(new Date() - 5000)); // Output: "5 seconds ago"