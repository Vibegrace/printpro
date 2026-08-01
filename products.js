// Products Page JavaScript
let allProducts = [];
let filteredProducts = [];

// Initialize products page
document.addEventListener('DOMContentLoaded', function() {
    loadProducts();
    setupFilters();
    setupSorting();
});

// Load products from API
function loadProducts() {
    const productsGrid = document.getElementById('productsGrid');
    productsGrid.innerHTML = '<div class="loading">Loading products...</div>';

    fetch('api/get-products.php')
        .then(response => response.json())
        .then(data => {
            allProducts = data;
            filteredProducts = [...allProducts];
            displayProducts(filteredProducts);
        })
        .catch(error => {
            console.error('Error loading products:', error);
            productsGrid.innerHTML = '<div class="no-products">Error loading products. Please try again later.</div>';
        });
}

// Display products in grid
function displayProducts(products) {
    const productsGrid = document.getElementById('productsGrid');
    
    if (products.length === 0) {
        productsGrid.innerHTML = '<div class="no-products">No products found matching your filters.</div>';
        return;
    }

    productsGrid.innerHTML = products.map(product => `
        <div class="product-card" onclick="viewProductDetail(${product.id})">
            <div class="product-image">
                <img src="${product.image}" alt="${product.product_name}" onerror="this.src='https://via.placeholder.com/200x200?text=No+Image'">
            </div>
            <div class="product-info">
                <div class="category">${product.category_name}</div>
                <h3>${product.product_name}</h3>
                <div class="price">₦${parseFloat(product.price).toLocaleString('en-NG', {minimumFractionDigits: 2})}</div>
                <button class="btn btn-primary btn-small" onclick="event.stopPropagation(); viewProductDetail(${product.id})">View Details</button>
            </div>
        </div>
    `).join('');
}

// Setup filters
function setupFilters() {
    const categoryFilters = document.querySelectorAll('.category-filter');
    
    categoryFilters.forEach(filter => {
        filter.addEventListener('change', applyFilters);
    });

    const priceFilter = document.getElementById('priceFilter');
    if (priceFilter) {
        priceFilter.addEventListener('input', applyFilters);
    }
}

// Apply filters
function applyFilters() {
    const selectedCategories = Array.from(document.querySelectorAll('.category-filter:checked'))
        .map(cb => cb.value);
    const maxPrice = parseFloat(document.getElementById('priceFilter')?.value || 1000000);
    const searchTerm = document.getElementById('searchProducts')?.value.toLowerCase() || '';

    filteredProducts = allProducts.filter(product => {
        const matchesCategory = selectedCategories.length === 0 || selectedCategories.includes(product.category_id.toString());
        const matchesPrice = parseFloat(product.price) <= maxPrice;
        const matchesSearch = product.product_name.toLowerCase().includes(searchTerm);
        
        return matchesCategory && matchesPrice && matchesSearch;
    });

    applySorting();
}

// Setup sorting
function setupSorting() {
    const sortSelect = document.getElementById('sortSelect');
    if (sortSelect) {
        sortSelect.addEventListener('change', applySorting);
    }
}

// Apply sorting
function applySorting() {
    const sortValue = document.getElementById('sortSelect')?.value || 'default';

    switch(sortValue) {
        case 'price-low':
            filteredProducts.sort((a, b) => parseFloat(a.price) - parseFloat(b.price));
            break;
        case 'price-high':
            filteredProducts.sort((a, b) => parseFloat(b.price) - parseFloat(a.price));
            break;
        case 'name-asc':
            filteredProducts.sort((a, b) => a.product_name.localeCompare(b.product_name));
            break;
        case 'newest':
            filteredProducts.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
            break;
        default:
            filteredProducts.sort((a, b) => a.id - b.id);
    }

    displayProducts(filteredProducts);
}

// View product detail
function viewProductDetail(productId) {
    const product = allProducts.find(p => p.id === productId);
    if (!product) return;

    const modal = document.getElementById('productModal');
    if (!modal) {
        console.error('Product modal not found');
        return;
    }

    document.getElementById('modalProductImage').src = product.image || 'https://via.placeholder.com/400x400?text=No+Image';
    document.getElementById('modalProductName').textContent = product.product_name;
    document.getElementById('modalProductCategory').textContent = product.category_name;
    document.getElementById('modalProductPrice').textContent = `₦${parseFloat(product.price).toLocaleString('en-NG', {minimumFractionDigits: 2})}`;
    document.getElementById('modalProductDescription').textContent = product.description || 'No description available';
    document.getElementById('modalProductDate').textContent = new Date(product.created_at).toLocaleDateString('en-NG');

    modal.style.display = 'block';
}

// Close modal
function closeProductModal() {
    const modal = document.getElementById('productModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

// Close modal when clicking outside
window.addEventListener('click', function(event) {
    const modal = document.getElementById('productModal');
    if (event.target === modal) {
        modal.style.display = 'none';
    }
});

// Search functionality
const searchInput = document.getElementById('searchProducts');
if (searchInput) {
    searchInput.addEventListener('input', applyFilters);
}
