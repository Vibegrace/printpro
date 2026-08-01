// Contact Form Handler
document.addEventListener('DOMContentLoaded', function() {
    setupContactForm();
    setupFAQ();
});

// Setup contact form
function setupContactForm() {
    const contactForm = document.getElementById('contactForm');
    if (!contactForm) return;

    contactForm.addEventListener('submit', function(e) {
        e.preventDefault();
        submitContactForm();
    });
}

// Submit contact form via AJAX
function submitContactForm() {
    const form = document.getElementById('contactForm');
    const formData = new FormData(form);
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;

    submitBtn.disabled = true;
    submitBtn.textContent = 'Sending...';

    fetch('api/send-contact.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        const messageDiv = document.getElementById('formMessage');
        
        if (data.success) {
            messageDiv.className = 'form-message';
            messageDiv.style.display = 'block';
            messageDiv.style.backgroundColor = '#d4edda';
            messageDiv.style.color = '#155724';
            messageDiv.style.border = '1px solid #c3e6cb';
            messageDiv.textContent = data.message;
            
            // Reset form
            form.reset();
            
            // Hide message after 5 seconds
            setTimeout(() => {
                messageDiv.style.display = 'none';
            }, 5000);
        } else {
            messageDiv.className = 'form-message';
            messageDiv.style.display = 'block';
            messageDiv.style.backgroundColor = '#f8d7da';
            messageDiv.style.color = '#721c24';
            messageDiv.style.border = '1px solid #f5c6cb';
            messageDiv.textContent = 'Error: ' + data.error;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        const messageDiv = document.getElementById('formMessage');
        messageDiv.className = 'form-message';
        messageDiv.style.display = 'block';
        messageDiv.style.backgroundColor = '#f8d7da';
        messageDiv.style.color = '#721c24';
        messageDiv.style.border = '1px solid #f5c6cb';
        messageDiv.textContent = 'Error sending message. Please try again.';
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
    });
}

// Setup FAQ accordion
function setupFAQ() {
    const faqItems = document.querySelectorAll('.faq-item');
    
    faqItems.forEach(item => {
        const question = item.querySelector('.faq-question');
        if (question) {
            question.addEventListener('click', function() {
                const answer = item.querySelector('.faq-answer');
                const isOpen = answer.style.display === 'block';
                
                // Close all other FAQs
                faqItems.forEach(otherItem => {
                    const otherAnswer = otherItem.querySelector('.faq-answer');
                    otherAnswer.style.display = 'none';
                    const toggle = otherItem.querySelector('.faq-toggle');
                    if (toggle) toggle.textContent = '+';
                });
                
                // Toggle current FAQ
                if (!isOpen) {
                    answer.style.display = 'block';
                    const toggle = item.querySelector('.faq-toggle');
                    if (toggle) toggle.textContent = '−';
                }
            });
        }
    });
}

// Price filter update (for contact page if needed)
const priceFilter = document.getElementById('priceFilter');
if (priceFilter) {
    priceFilter.addEventListener('input', function() {
        const priceValue = document.getElementById('priceValue');
        if (priceValue) {
            priceValue.textContent = '₦' + parseInt(this.value).toLocaleString('en-NG');
        }
    });
}
