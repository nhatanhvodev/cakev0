document.addEventListener('DOMContentLoaded', () => {
  console.log('DOMContentLoaded fired');
  // displayGreeting(); // Comment để tránh ghi đè nội dung từ PHP
  setupSearch();
  updateCart();
  initAddToCartButtons();
  initModalAuth();
  initRegisterButtons();
  if (document.querySelector('#list-comment')) {
    initCommentSlider();
  }
  if (document.querySelector('#write-post') || document.querySelector('#posts')) {
    initBlog();
  }

  const cartBtn = document.querySelector('#cart-btn');
  const blogBtn = document.querySelector('#blog-btn');
  if (cartBtn) cartBtn.addEventListener('click', goToCart);
  if (blogBtn) blogBtn.addEventListener('click', goToBlog);
});

// Comment hoặc xóa hàm displayGreeting
/*
function displayGreeting() {
  console.log('displayGreeting called');
  const user = localStorage.getItem('loggedInUser');
  const greeting = document.getElementById('user-greeting');
  if (greeting) {
    if (user) {
      greeting.innerHTML = `👋 Xin chào, <strong>${user}</strong>! <a href="logout.php" class="logout-btn">Đăng xuất</a>`;
    } else {
      greeting.innerHTML = `<a href="login.php">Đăng nhập / Đăng ký</a>`;
    }
  }
}
*/

// Comment Slider
function initCommentSlider() {
  console.log('initCommentSlider called');
  const nextBtn = document.querySelector('.next');
  const prevBtn = document.querySelector('.prev');
  const commentList = document.querySelector('#list-comment');
  const items = document.querySelectorAll('#list-comment .item');

  let currentIndex = 0;
  const totalItems = items.length;
  const itemWidth = items[0] ? items[0].offsetWidth + 20 : 0; // Include margin

  function updateButtons() {
    if (prevBtn && nextBtn) {
      prevBtn.classList.toggle('disabled', currentIndex === 0);
      nextBtn.classList.toggle('disabled', currentIndex >= totalItems - 1);
    }
  }

  if (nextBtn) {
    nextBtn.addEventListener('click', () => {
      if (currentIndex < totalItems - 1) {
        currentIndex++;
        commentList.style.transform = `translateX(-${itemWidth * currentIndex}px)`;
        updateButtons();
        console.log(`Slider moved to index ${currentIndex}`);
      }
    });
  }

  if (prevBtn) {
    prevBtn.addEventListener('click', () => {
      if (currentIndex > 0) {
        currentIndex--;
        commentList.style.transform = `translateX(-${itemWidth * currentIndex}px)`;
        updateButtons();
        console.log(`Slider moved to index ${currentIndex}`);
      }
    });
  }

  updateButtons();
}

// Register Handler
function handleRegister(event) {
  event.preventDefault();
  const username = document.getElementById('new-username').value;
  const password = document.getElementById('new-password').value;
  const confirmPassword = document.getElementById('confirm-password').value;

  if (password.length < 8) {
    document.getElementById('password-error').style.display = 'block';
    return;
  } else {
    document.getElementById('password-error').style.display = 'none';
  }

  if (password !== confirmPassword) {
    document.getElementById('confirm-password-error').style.display = 'block';
    return;
  } else {
    document.getElementById('confirm-password-error').style.display = 'none';
  }

  let users = JSON.parse(localStorage.getItem('users')) || [];
  if (users.some(user => user.username === username)) {
    window.showToast('Tài khoản đã tồn tại!', 'error');
    return;
  }

  users.push({ username, password });
  localStorage.setItem('users', JSON.stringify(users));
  window.showToast('Đăng ký thành công! Hãy đăng nhập.', 'success');
  window.location.href = 'login.php';
}

// Login Handler
function handleLogin(event) {
  event.preventDefault();
  const username = document.getElementById('username').value;
  const password = document.getElementById('password').value;

  const users = JSON.parse(localStorage.getItem('users')) || [];
  const matchedUser = users.find(user => user.username === username && user.password === password);

  if (!matchedUser) {
    window.showToast('Tên đăng nhập hoặc mật khẩu không đúng!', 'error');
    return;
  }

  window.showToast('Đăng nhập thành công!', 'success');

  // Handle redirect after login
  const urlParams = new URLSearchParams(window.location.search);
  const redirect = urlParams.get('redirect');
  window.location.href = redirect ? decodeURIComponent(redirect) : 'index.php';
}

// Logout Handler
function logout() {
  console.log('logout called');
  window.location.href = 'logout.php';
}

// Add to Cart
function addToCart(id, name, price, image, quantity = 1) {
  console.log(`addToCart called with: id=${id}, name=${name}, price=${price}, image=${image}, quantity=${quantity}`);
  const user = localStorage.getItem('loggedInUser');
  if (!user) {
    const currentPage = window.location.pathname.split('/').pop();
    window.location.href = `login.php?redirect=${currentPage}`;
    return;
  }

  // Validate inputs
  if (!id || isNaN(id)) {
    console.error('Invalid product ID:', id);
    window.showToast('ID sản phẩm không hợp lệ!', 'error');
    return;
  }
  if (!name || typeof name !== 'string') {
    console.error('Invalid product name:', name);
    window.showToast('Tên sản phẩm không hợp lệ!', 'error');
    return;
  }
  if (!price || isNaN(price) || price <= 0) {
    console.error('Invalid product price:', price);
    window.showToast('Giá sản phẩm không hợp lệ!', 'error');
    return;
  }
  if (!image || typeof image !== 'string') {
    console.error('Invalid product image:', image);
    window.showToast('Hình ảnh sản phẩm không hợp lệ!', 'error');
    return;
  }
  if (isNaN(quantity) || quantity <= 0) {
    console.error('Invalid quantity:', quantity);
    window.showToast('Số lượng không hợp lệ!', 'error');
    return;
  }

  let cart = JSON.parse(localStorage.getItem('cart')) || [];
  const existingItem = cart.find(item => item.id === parseInt(id));
  if (existingItem) {
    existingItem.quantity += quantity;
  } else {
    cart.push({
      id: parseInt(id),
      name: name,
      price: parseFloat(price),
      image: image,
      quantity: quantity
    });
  }

  localStorage.setItem('cart', JSON.stringify(cart));
  window.showToast(`Đã thêm ${quantity} x ${name} vào giỏ hàng!`, 'success');
  updateCart();
}

// Update Cart
function updateCart() {
  console.log('updateCart called');
  const user = localStorage.getItem('loggedInUser');
  if (!user) return;

  const cart = JSON.parse(localStorage.getItem('cart')) || [];
  const cartTable = document.querySelector('#cart-list');
  const emptyMessage = document.querySelector('#empty-cart-message');

  if (!cartTable || !emptyMessage) return;

  // Show/hide empty cart message
  if (cart.length === 0) {
    cartTable.parentElement.style.display = 'none';
    emptyMessage.style.display = 'block';
  } else {
    cartTable.parentElement.style.display = 'block';
    emptyMessage.style.display = 'none';
  }

  cartTable.innerHTML = ''; // Clear existing rows

  cart.forEach((item, index) => {
    const row = document.createElement('tr');
    row.innerHTML = `
      <td>
        <button class="btn btn-danger btn-sm" onclick="removeFromCart(${index})">
          <i class="bi bi-x-lg"></i>
        </button>
      </td>
      <td><img src="${item.image || '/Cake/img/no-image.jpg'}" alt="${item.name}" width="100" height="100"></td>
      <td>${item.name}</td>
      <td>${item.price.toLocaleString('vi-VN')} VNĐ</td>
      <td>
        <input type="number" class="form-control w-50 mx-auto" value="${item.quantity}" min="1" onchange="updateQuantity(${index}, this.value)">
      </td>
      <td>${(item.price * item.quantity).toLocaleString('vi-VN')} VNĐ</td>
    `;
    cartTable.appendChild(row);
  });

  // Calculate subtotal and total
  const subtotal = cart.reduce((total, item) => total + item.price * item.quantity, 0);
  const couponDiscount = getCouponDiscount(user, subtotal);
  const total = subtotal - couponDiscount;

  const subtotalElement = document.querySelector('#cart-subtotal');
  const totalElement = document.querySelector('#cart-total');
  if (subtotalElement && totalElement) {
    subtotalElement.textContent = `${subtotal.toLocaleString('vi-VN')} VNĐ`;
    totalElement.textContent = `${total.toLocaleString('vi-VN')} VNĐ`;
  }
}

// Update Quantity
function updateQuantity(index, newQuantity) {
  console.log(`updateQuantity called: index=${index}, newQuantity=${newQuantity}`);
  const user = localStorage.getItem('loggedInUser');
  if (!user) return;

  let cart = JSON.parse(localStorage.getItem('cart')) || [];
  newQuantity = parseInt(newQuantity);
  if (isNaN(newQuantity) || newQuantity < 1) {
    removeFromCart(index);
    return;
  }

  cart[index].quantity = newQuantity;
  localStorage.setItem('cart', JSON.stringify(cart));
  updateCart();
}

// Remove from Cart
function removeFromCart(index) {
  console.log(`removeFromCart called: index=${index}`);
  const user = localStorage.getItem('loggedInUser');
  if (!user) return;

  let cart = JSON.parse(localStorage.getItem('cart')) || [];
  cart.splice(index, 1);
  localStorage.setItem('cart', JSON.stringify(cart));
  updateCart();
}

// Apply Coupon
function applyCoupon() {
  const user = localStorage.getItem('loggedInUser');
  if (!user) {
    window.showToast('Vui lòng đăng nhập để dùng mã giảm giá!', 'error');
    window.location.href = 'login.php';
    return;
  }

  const couponCode = document.getElementById('coupon-code').value.trim();
  if (!couponCode) {
    window.showToast('Vui lòng nhập mã giảm giá!', 'error');
    return;
  }

  // Simple coupon system
  const validCoupons = {
    'SAVE10': { type: 'percentage', value: 10 }, // 10% off
    'DISCOUNT5000': { type: 'fixed', value: 5000 } // 5000 VNĐ off
  };

  if (!validCoupons[couponCode]) {
    window.showToast('Mã giảm giá không hợp lệ!', 'error');
    return;
  }

  const couponKey = `coupon_${user}`;
  localStorage.setItem(couponKey, JSON.stringify({ code: couponCode, ...validCoupons[couponCode] }));
  window.showToast('Áp dụng mã giảm giá thành công!', 'success');
  updateCart();
}

// Get Coupon Discount
function getCouponDiscount(user, subtotal) {
  const couponKey = `coupon_${user}`;
  const coupon = JSON.parse(localStorage.getItem(couponKey));
  if (!coupon) return 0;

  if (coupon.type === 'percentage') {
    return (subtotal * coupon.value) / 100;
  } else if (coupon.type === 'fixed') {
    return Math.min(coupon.value, subtotal);
  }
  return 0;
}

// Checkout
function checkout() {
  console.log('checkout called');
  const user = localStorage.getItem('loggedInUser');
  if (!user) {
    window.showToast('Vui lòng đăng nhập để thanh toán!', 'error');
    window.location.href = 'login.php';
    return;
  }

  const cart = JSON.parse(localStorage.getItem('cart')) || [];
  if (cart.length === 0) {
    window.showToast('Giỏ hàng của bạn đang trống!', 'error');
    return;
  }

  document.getElementById('cart').style.display = 'none';
  document.getElementById('checkout-form').style.display = 'block';
}

// Complete Checkout
function completeCheckout(event) {
  event.preventDefault();
  console.log('completeCheckout called');
  const user = localStorage.getItem('loggedInUser');
  if (!user) return;

  const recipientName = document.getElementById('recipient-name').value;
  const phone = document.getElementById('phone').value;
  const address = document.getElementById('address').value;

  if (!recipientName || !phone || !address) {
    window.showToast('Vui lòng điền đầy đủ thông tin!', 'error');
    return;
  }

  // Log the order (in a real app, send to a backend)
  console.log('Order placed:', {
    user,
    recipientName,
    phone,
    address,
    cart: JSON.parse(localStorage.getItem('cart'))
  });

  // Clear cart and coupon
  localStorage.removeItem('cart');
  localStorage.removeItem(`coupon_${user}`);

  window.showToast('Thanh toán thành công! Cảm ơn bạn!', 'success');
  window.location.href = 'index.php';
}

// Search Functionality
function setupSearch() {
  console.log('setupSearch called');
  const searchButton = document.querySelector('.search-Click');
  const searchForm = document.querySelector('.nav-menu__formsearchheader');
  if (!searchButton || !searchForm) return;

  searchButton.addEventListener('click', () => {
    searchForm.classList.toggle('activeS');
    const icon = searchButton.querySelector('i');
    if (icon) {
      icon.classList.toggle('fa-xmark');
    }
  });

  const searchInput = searchForm.querySelector('input[name="keywords"]');
  searchInput.addEventListener('input', function() {
    const keywords = this.value;
    const type = document.getElementById('type').value;
    if (keywords.length > 0) {
      fetch(`ajax/autoCompleteSearch.php?keywords=${encodeURIComponent(keywords)}&type=${type}`)
        .then(response => response.json())
        .then(data => {
          const autocompleteDiv = document.querySelector('.autocomplete_show');
          autocompleteDiv.innerHTML = '';
          data.forEach(item => {
            const div = document.createElement('div');
            div.textContent = item.name;
            div.addEventListener('click', () => {
              window.location.href = `product.php?loai=${item.category}`;
            });
            autocompleteDiv.appendChild(div);
          });
        })
        .catch(error => console.error('Error fetching autocomplete data:', error));
    } else {
      document.querySelector('.autocomplete_show').innerHTML = '';
    }
  });
}

// Modal Authentication
function initModalAuth() {
  const cartBtn = document.querySelector('a[href$="cart.php"] button');
  const modal = document.getElementById('auth-modal');
  const closeBtn = document.querySelector('.close-modal');
  const tabLogin = document.getElementById('tab-login');
  const tabRegister = document.getElementById('tab-register');
  const loginForm = document.getElementById('login-form');
  const registerForm = document.getElementById('register-form');

  if (cartBtn && modal) {
    cartBtn.addEventListener('click', e => {
      const user = localStorage.getItem('loggedInUser');
      if (!user) {
        e.preventDefault();
        modal.style.display = 'flex';
      }
    });
  }

  if (closeBtn) {
    closeBtn.addEventListener('click', () => modal.style.display = 'none');
  }

  if (modal) {
    window.addEventListener('click', e => {
      if (e.target === modal) modal.style.display = 'none';
    });
  }

  if (tabLogin && tabRegister) {
    tabLogin.addEventListener('click', () => {
      tabLogin.classList.add('active');
      tabRegister.classList.remove('active');
      loginForm.style.display = 'block';
      registerForm.style.display = 'none';
    });
    tabRegister.addEventListener('click', () => {
      tabRegister.classList.add('active');
      tabLogin.classList.remove('active');
      registerForm.style.display = 'block';
      loginForm.style.display = 'none';
    });
  }

  if (loginForm) loginForm.addEventListener('submit', handleLogin);
  if (registerForm) registerForm.addEventListener('submit', handleRegister);
}

// Register Button Handlers
function initRegisterButtons() {
  document.querySelectorAll('.btn-open-register').forEach(button => {
    button.addEventListener('click', () => {
      document.getElementById('register-form').style.display = 'block';
      window.scrollTo({ top: document.getElementById('register-form').offsetTop, behavior: 'smooth' });
    });
  });
}

// Initialize Add to Cart Buttons
function initAddToCartButtons() {
  console.log('initAddToCartButtons called');
  document.body.addEventListener('click', (e) => {
    const button = e.target.closest('.add-to-cart');
    if (button) {
      const productCard = button.closest('.product-card');
      if (productCard) {
        const id = parseInt(button.dataset.banhId);
        if (isNaN(id) || id <= 0) {
          console.error('Invalid banh_id:', button.dataset.banhId);
          window.showToast('ID sản phẩm không hợp lệ!', 'error');
          return;
        }
        const name = button.dataset.name;
        const price = parseFloat(button.dataset.price);
        const image = button.dataset.image;
        console.log(`Add to cart clicked: id=${id}, name=${name}, price=${price}, image=${image}`);
        // The modal is triggered in index.php, so we don't call addToCart here
      }
    }
  });
}

// Blog Functions
function addPost(event) {
  event.preventDefault();
  console.log('addPost called');
  const user = localStorage.getItem('loggedInUser');
  if (!user) {
    const redirect = encodeURIComponent(location.pathname + location.search);
    window.showToast('Vui lòng đăng nhập để viết bài!', 'error');
    window.location.href = `login.php?redirect=${redirect}`;
    return;
  }

  const title = document.getElementById('post-title').value.trim();
  const content = document.getElementById('post-content').value.trim();
  if (!title || !content) {
    window.showToast('Vui lòng điền đầy đủ tiêu đề và nội dung!', 'error');
    return;
  }

  // Simple spam prevention: Limit to 1 post per minute per user
  const lastPostKey = `lastPost_${user}`;
  const lastPostTime = localStorage.getItem(lastPostKey);
  const now = Date.now();
  if (lastPostTime && (now - lastPostTime < 60000)) {
    window.showToast('Vui lòng đợi 1 phút trước khi đăng bài mới!', 'error');
    return;
  }

  const postsKey = `posts_${user}`;
  const posts = JSON.parse(localStorage.getItem(postsKey) || '[]');
  const newPost = {
    title,
    content,
    author: user,
    timestamp: now,
    date: new Date(now).toLocaleString()
  };
  posts.push(newPost);
  localStorage.setItem(postsKey, JSON.stringify(posts));
  localStorage.setItem(lastPostKey, now);

  displayPosts();
  event.target.reset();
  alert('Bài viết của bạn đã được đăng!');
}

function displayPosts() {
  console.log('displayPosts called');
  const user = localStorage.getItem('loggedInUser');
  const postsContainer = document.getElementById('posts-list');
  if (!postsContainer) return;

  const allPosts = [];
  const users = JSON.parse(localStorage.getItem('users') || '[]');
  users.forEach(u => {
    const userPosts = JSON.parse(localStorage.getItem(`posts_${u.username}`) || '[]');
    userPosts.forEach(post => {
      allPosts.push(post);
    });
  });

  // Sort posts by timestamp (newest first)
  allPosts.sort((a, b) => b.timestamp - a.timestamp);

  if (allPosts.length === 0) {
    postsContainer.innerHTML = '<p>Chưa có bài viết nào.</p>';
    return;
  }

  postsContainer.innerHTML = ''; // Clear existing posts
  allPosts.forEach(post => {
    const postElement = document.createElement('div');
    postElement.className = 'post';
    postElement.innerHTML = `
      <h3>${post.title}</h3>
      <div class="meta">Đăng bởi: ${post.author} | ${post.date}</div>
      <div class="post-content">${post.content}</div>
      ${user === post.author ? `
        <div class="blog-actions">
          <a href="#" onclick="editPost('${post.timestamp}', '${user}'); return false;"><i class="fa fa-edit"></i>Chỉnh sửa</a>
          <a href="#" onclick="deletePost('${post.timestamp}', '${user}'); return false;"><i class="fa fa-trash"></i>Xóa</a>
        </div>` : ''}
    `;
    postsContainer.appendChild(postElement);
  });
}

function editPost(timestamp, user) {
  const postsKey = `posts_${user}`;
  const posts = JSON.parse(localStorage.getItem(postsKey) || '[]');
  const post = posts.find(p => p.timestamp == timestamp);
  if (!post) return;

  const newTitle = prompt('Nhập tiêu đề mới:', post.title);
  const newContent = prompt('Nhập nội dung mới:', post.content);
  if (newTitle && newContent) {
    post.title = newTitle.trim();
    post.content = newContent.trim();
    localStorage.setItem(postsKey, JSON.stringify(posts));
    displayPosts();
    window.showToast('Bài viết đã được cập nhật!', 'success');
  }
}

function deletePost(timestamp, user) {
  if (!confirm('Bạn có chắc muốn xóa bài viết này?')) return;

  const postsKey = `posts_${user}`;
  let posts = JSON.parse(localStorage.getItem(postsKey) || '[]');
  posts = posts.filter(p => p.timestamp != timestamp);
  localStorage.setItem(postsKey, JSON.stringify(posts));
  displayPosts();
  window.showToast('Bài viết đã được xóa!', 'success');
}

// Check Login Status for Header Actions
function checkLoginForAction(actionFn) {
  const user = localStorage.getItem('loggedInUser');
  if (!user) {
    const currentPage = window.location.pathname.split('/').pop();
    window.location.href = `login.php?redirect=${currentPage}`;
    return false;
  }
  actionFn();
  return true;
}

// Header Action Functions
function goToCart() {
  checkLoginForAction(() => window.location.href = 'cart.php');
}

function goToBlog() {
  checkLoginForAction(() => window.location.href = 'blog.php');
}

// Initialize Blog Section
function initBlog() {
  console.log('initBlog called');
  const user = localStorage.getItem('loggedInUser');
  const writePostSection = document.querySelector('#write-post');
  if (writePostSection) {
    if (!user) {
      const redirect = encodeURIComponent(location.pathname + location.search);
      writePostSection.innerHTML = `
        <p style="text-align:center; font-size:16px; padding:20px;">
          Bạn cần <a href="login.php?redirect=${redirect}">đăng nhập</a> để viết bài.
        </p>`;
    } else {
      const form = document.getElementById('post-form');
      if (form) form.addEventListener('submit', addPost);
    }
  }
  displayPosts();
}

// Function to update cart display (used in cart.php)
function displayCart() {
  const cart = JSON.parse(localStorage.getItem('cart')) || [];
  const cartList = document.getElementById('cart-list');
  if (cartList) {
    cartList.innerHTML = '';
    let total = 0;
    cart.forEach(item => {
      const itemTotal = item.price * item.quantity;
      total += itemTotal;
      const li = document.createElement('li');
      li.innerHTML = `
        <img src="${item.image || '/Cake/img/no-image.jpg'}" alt="${item.name}" width="50" height="50">
        ${item.name} - ${item.quantity} x ${item.price.toLocaleString('vi-VN')} VNĐ = ${itemTotal.toLocaleString('vi-VN')} VNĐ
      `;
      cartList.appendChild(li);
    });
    const totalElement = document.createElement('li');
    totalElement.textContent = `Tổng cộng: ${total.toLocaleString('vi-VN')} VNĐ`;
    cartList.appendChild(totalElement);
  }
}

// Run displayCart on page load for cart.php
document.addEventListener('DOMContentLoaded', () => {
  if (document.getElementById('cart-list')) {
    displayCart();
  }
});