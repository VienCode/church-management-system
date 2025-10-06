document.addEventListener("DOMContentLoaded", function() {
    const scrollBtn = document.getElementById("aboutus_btn");
    const target = document.getElementById("aboutus_section");

    scrollBtn.addEventListener("click", function() {
        target.scrollIntoView({behavior: "smooth"});
    })
})

// Attendance Management JavaScript Functions

// Toggle time input based on attendance status
function toggleTimeInput(memberId, status) {
    const timeInput = document.getElementById('time_' + memberId);
    if (status === 'Present') {
        timeInput.disabled = false;
        if (!timeInput.value) {
            // Set current time if not already set
            const now = new Date();
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            timeInput.value = `${hours}:${minutes}`;
        }
    } else {
        timeInput.disabled = true;
        timeInput.value = '';
    }
}

// Update button styles for browsers that don't support :has()
function updateButtonStyle(radioInput) {
    // Get the parent label
    const label = radioInput.closest('label');
    const radioGroup = label.parentElement;
    
    // Remove checked class from all labels in this group
    const allLabels = radioGroup.querySelectorAll('label');
    allLabels.forEach(l => l.classList.remove('checked'));
    
    // Add checked class to the selected label
    if (radioInput.checked) {
        label.classList.add('checked');
    }
}

// Mark all as present
function markAllPresent() {
    const radios = document.querySelectorAll('input[type="radio"][value="Present"]');
    radios.forEach(radio => {
        radio.checked = true;
        updateButtonStyle(radio);
        
        // Extract member ID from the name attribute
        const match = radio.name.match(/\[(\d+)\]/);
        if (match) {
            toggleTimeInput(match[1], 'Present');
        }
    });
}

// Mark all as absent
function markAllAbsent() {
    const radios = document.querySelectorAll('input[type="radio"][value="Absent"]');
    radios.forEach(radio => {
        radio.checked = true;
        updateButtonStyle(radio);
        
        // Extract member ID from the name attribute
        const match = radio.name.match(/\[(\d+)\]/);
        if (match) {
            toggleTimeInput(match[1], 'Absent');
        }
    });
}

// Clear all selections
function clearAll() {
    const radios = document.querySelectorAll('input[type="radio"]');
    radios.forEach(radio => {
        radio.checked = false;
        // Remove checked class from labels for fallback styling
        const label = radio.closest('label');
        if (label) {
            label.classList.remove('checked');
        }
    });
    
    const timeInputs = document.querySelectorAll('.time-input');
    timeInputs.forEach(input => {
        input.disabled = true;
        input.value = '';
    });
}

// Modal functions
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('show');
        document.body.style.overflow = 'hidden'; // Prevent background scrolling
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('show');
        document.body.style.overflow = 'auto'; // Restore scrolling
    }
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        if (event.target === modal) {
            modal.classList.remove('show');
            document.body.style.overflow = 'auto';
        }
    });
}

// Church Updates JavaScript Functions

// Image preview functionality
function previewImage(input, previewId) {
    const preview = document.getElementById(previewId);
    preview.innerHTML = '';
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            const img = document.createElement('img');
            img.src = e.target.result;
            img.style.maxWidth = '200px';
            img.style.maxHeight = '150px';
            img.style.borderRadius = '8px';
            img.style.boxShadow = '0 2px 8px rgba(0,0,0,0.1)';
            preview.appendChild(img);
        }
        
        reader.readAsDataURL(input.files[0]);
    }
}

// Open edit modal with post data
function openEditModal(postId) {
    // Fetch post data via AJAX
    fetch(`get_post.php?id=${postId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const post = data.post;
                document.getElementById('editPostId').value = post.id;
                document.getElementById('editTitle').value = post.title;
                document.getElementById('editContent').value = post.content;
                
                // Show current image if exists
                const currentImageContainer = document.getElementById('currentImageContainer');
                if (post.image) {
                    currentImageContainer.innerHTML = `
                        <div class="current-image">
                            <h4>Current Image:</h4>
                            <img src="${post.image}" style="max-width: 200px; max-height: 150px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                        </div>
                    `;
                } else {
                    currentImageContainer.innerHTML = '';
                }
                
                // Clear edit preview
                document.getElementById('editPreview').innerHTML = '';
                
                openModal('editPostModal');
            } else {
                alert('Failed to load post data');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while loading the post');
        });
}

// Delete post with confirmation
function deletePost(postId) {
    if (confirm('Are you sure you want to delete this post? This action cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        const postIdInput = document.createElement('input');
        postIdInput.type = 'hidden';
        postIdInput.name = 'post_id';
        postIdInput.value = postId;
        
        const deleteInput = document.createElement('input');
        deleteInput.type = 'hidden';
        deleteInput.name = 'delete_post';
        deleteInput.value = '1';
        
        form.appendChild(postIdInput);
        form.appendChild(deleteInput);
        document.body.appendChild(form);
        form.submit();
    }
}

// Lightbox functionality
function openLightbox(imageSrc) {
    const lightbox = document.getElementById('lightbox');
    const lightboxImage = document.getElementById('lightbox-image');
    
    lightboxImage.src = imageSrc;
    lightbox.classList.add('show');
    lightbox.style.display = 'flex';
    
    // Prevent body scrolling
    document.body.style.overflow = 'hidden';
}

function closeLightbox() {
    const lightbox = document.getElementById('lightbox');
    lightbox.classList.remove('show');
    
    setTimeout(() => {
        lightbox.style.display = 'none';
        document.body.style.overflow = 'auto';
    }, 300);
}

// Keyboard support for lightbox
document.addEventListener('keydown', function(e) {
    const lightbox = document.getElementById('lightbox');
    if (lightbox && lightbox.classList.contains('show')) {
        if (e.key === 'Escape') {
            closeLightbox();
        }
    }
});

// Close lightbox when clicking outside image
document.addEventListener('DOMContentLoaded', function() {
    const lightbox = document.getElementById('lightbox');
    if (lightbox) {
        lightbox.addEventListener('click', function(e) {
            if (e.target === this) {
                closeLightbox();
            }
        });
    }
});

// Form validation
document.addEventListener('DOMContentLoaded', function() {
    // Add form validation for create post
    const createForm = document.querySelector('#createPostModal form');
    if (createForm) {
        createForm.addEventListener('submit', function(e) {
            const title = this.querySelector('[name="title"]').value.trim();
            const content = this.querySelector('[name="content"]').value.trim();
            
            if (!title || !content) {
                e.preventDefault();
                alert('Please fill in both title and content fields.');
                return;
            }
            
            if (title.length > 200) {
                e.preventDefault();
                alert('Title must be less than 200 characters.');
                return;
            }
        });
    }
    
    // Add form validation for edit post
    const editForm = document.querySelector('#editPostModal form');
    if (editForm) {
        editForm.addEventListener('submit', function(e) {
            const title = this.querySelector('[name="title"]').value.trim();
            const content = this.querySelector('[name="content"]').value.trim();
            
            if (!title || !content) {
                e.preventDefault();
                alert('Please fill in both title and content fields.');
                return;
            }
            
            if (title.length > 200) {
                e.preventDefault();
                alert('Title must be less than 200 characters.');
                return;
            }
        });
    }
    
    // Auto-resize textareas
    const textareas = document.querySelectorAll('textarea');
    textareas.forEach(textarea => {
        textarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = this.scrollHeight + 'px';
        });
    });
});

// Image upload validation
function validateImageUpload(input) {
    const file = input.files[0];
    if (file) {
        // Check file size (5MB limit)
        const maxSize = 5 * 1024 * 1024; // 5MB in bytes
        if (file.size > maxSize) {
            alert('Image file size must be less than 5MB.');
            input.value = '';
            return false;
        }
        
        // Check file type
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        if (!allowedTypes.includes(file.type)) {
            alert('Please upload a valid image file (JPEG, PNG, GIF, or WebP).');
            input.value = '';
            return false;
        }
    }
    return true;
}

// Add image validation to file inputs
document.addEventListener('DOMContentLoaded', function() {
    const fileInputs = document.querySelectorAll('input[type="file"][accept*="image"]');
    fileInputs.forEach(input => {
        input.addEventListener('change', function() {
            validateImageUpload(this);
        });
    });
});

// Smooth scroll to top after form submission
function scrollToTop() {
    window.scrollTo({
        top: 0,
        behavior: 'smooth'
    });
}

// Auto-hide success messages
document.addEventListener('DOMContentLoaded', function() {
    const successMessage = document.querySelector('.success-message');
    if (successMessage) {
        setTimeout(() => {
            successMessage.style.opacity = '0';
            successMessage.style.transform = 'translateY(-10px)';
            setTimeout(() => {
                successMessage.style.display = 'none';
            }, 300);
        }, 5000);
    }
});

// Loading state for forms
function showLoading(button) {
    const originalText = button.textContent;
    button.textContent = 'Processing...';
    button.disabled = true;
    button.style.opacity = '0.7';
    
    return originalText;
}

function hideLoading(button, originalText) {
    button.textContent = originalText;
    button.disabled = false;
    button.style.opacity = '1';
}

// Enhanced form submission with loading states
document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const submitButton = this.querySelector('button[type="submit"]');
            if (submitButton) {
                showLoading(submitButton);
            }
        });
    });
});

// Ensure time inputs are correctly enabled/disabled on page load
function setInitialTimeInputState() {
    const rows = document.querySelectorAll('.attendance-table tbody tr');
    rows.forEach(row => {
        const presentRadio = row.querySelector('input[type="radio"][value="Present"]');
        const timeInput = row.querySelector('.time-input');

        if (presentRadio && presentRadio.checked) {
            timeInput.disabled = false;
        } else {
            timeInput.disabled = true;
            timeInput.value = '';
        }
    });
}

// Run once the page has fully loaded
document.addEventListener('DOMContentLoaded', setInitialTimeInputState);
