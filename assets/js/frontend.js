/**
 * Frontend JavaScript for SimpleLMS.
 * 
 * Handles lesson completion toggling using Vanilla JS.
 */
document.addEventListener('DOMContentLoaded', function () {
    const completeButtons = document.querySelectorAll('.slms-complete-toggle');

    completeButtons.forEach(button => {
        button.addEventListener('click', function (e) {
            e.preventDefault();

            const btn = e.currentTarget;
            const data = {
                user_id: parseInt(btn.dataset.userId, 10),
                course_id: parseInt(btn.dataset.courseId, 10),
                lesson_id: parseInt(btn.dataset.lessonId, 10),
                completed: !btn.classList.contains('is-completed')
            };

            // Disable button and show loading state
            btn.disabled = true;
            btn.style.opacity = '0.5';

            fetch(btn.dataset.restUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': btn.dataset.nonce
                },
                body: JSON.stringify(data)
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(result => {
                    if (result.success) {
                        if (data.completed) {
                            btn.classList.add('is-completed', 'button-primary');
                            btn.classList.remove('button-secondary');
                        } else {
                            btn.classList.remove('is-completed', 'button-primary');
                            btn.classList.add('button-secondary');
                        }
                    }
                })
                .catch(error => {
                    console.error('SimpleLMS: Error toggling lesson completion.', error);
                })
                .finally(() => {
                    // Re-enable button
                    btn.disabled = false;
                    btn.style.opacity = '1';
                });
        });
    });
});
