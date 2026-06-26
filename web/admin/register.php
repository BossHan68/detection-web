<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>สมัครสมาชิก | Fall Detection System</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    :root {
        --primary-color: #4f46e5;
        --danger-color: #ef4444;
        --success-color: #10b981;
        --dark-bg: #1e293b;
    }
    
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }
    
    body {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        padding: 20px;
    }
    
    .register-container {
        max-width: 500px;
        width: 100%;
    }
    
    .register-card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border-radius: 20px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        overflow: hidden;
        border: 1px solid rgba(255, 255, 255, 0.3);
    }
    
    .register-header {
        background: linear-gradient(135deg, #10b981, #059669);
        padding: 40px 30px;
        text-align: center;
        color: white;
    }
    
    .register-header i {
        font-size: 3.5rem;
        margin-bottom: 15px;
        animation: bounce 2s infinite;
    }
    
    .register-header h4 {
        font-weight: 700;
        margin: 0 0 8px 0;
        font-size: 1.8rem;
    }
    
    .register-header p {
        margin: 0;
        opacity: 0.9;
        font-size: 0.95rem;
    }
    
    .register-body {
        padding: 40px 35px;
    }
    
    .alert {
        border-radius: 12px;
        border: none;
        padding: 15px 20px;
        margin-bottom: 25px;
        display: flex;
        align-items: center;
        gap: 12px;
        animation: slideDown 0.3s ease;
    }
    
    .alert i {
        font-size: 1.3rem;
    }
    
    .alert-danger {
        background: linear-gradient(135deg, #fee2e2, #fecaca);
        color: var(--danger-color);
    }
    
    .alert-success {
        background: linear-gradient(135deg, #d1fae5, #a7f3d0);
        color: var(--success-color);
    }
    
    .form-group {
        margin-bottom: 20px;
        position: relative;
    }
    
    .form-label {
        display: block;
        margin-bottom: 8px;
        color: var(--dark-bg);
        font-weight: 600;
        font-size: 0.9rem;
    }
    
    .input-wrapper {
        position: relative;
    }
    
    .input-icon {
        position: absolute;
        left: 18px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: 1.1rem;
    }
    
    .form-control {
        width: 100%;
        padding: 14px 18px 14px 50px;
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        font-size: 1rem;
        transition: all 0.3s ease;
        background: #f8fafc;
    }
    
    .form-control:focus {
        outline: none;
        border-color: var(--success-color);
        background: white;
        box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1);
    }
    
    .form-control::placeholder {
        color: #cbd5e1;
    }
    
    .password-strength {
        margin-top: 8px;
        font-size: 0.85rem;
    }
    
    .strength-bar {
        height: 4px;
        background: #e2e8f0;
        border-radius: 2px;
        overflow: hidden;
        margin-top: 5px;
    }
    
    .strength-bar-fill {
        height: 100%;
        width: 0%;
        transition: all 0.3s ease;
        background: #ef4444;
    }
    
    .strength-weak .strength-bar-fill {
        width: 33%;
        background: #ef4444;
    }
    
    .strength-medium .strength-bar-fill {
        width: 66%;
        background: #f59e0b;
    }
    
    .strength-strong .strength-bar-fill {
        width: 100%;
        background: #10b981;
    }
    
    .btn-register {
        width: 100%;
        padding: 15px;
        background: linear-gradient(135deg, var(--success-color), #059669);
        color: white;
        border: none;
        border-radius: 12px;
        font-size: 1.05rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        margin-top: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
    }
    
    .btn-register:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px rgba(16, 185, 129, 0.4);
    }
    
    .btn-register:active {
        transform: translateY(0);
    }
    
    .login-link {
        text-align: center;
        margin-top: 25px;
        padding-top: 25px;
        border-top: 1px solid #e2e8f0;
    }
    
    .login-link a {
        color: var(--primary-color);
        text-decoration: none;
        font-weight: 600;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    .login-link a:hover {
        color: #7c3aed;
        gap: 12px;
    }
    
    .floating-shapes {
        position: fixed;
        width: 100%;
        height: 100%;
        top: 0;
        left: 0;
        z-index: -1;
        overflow: hidden;
        pointer-events: none;
    }
    
    .shape {
        position: absolute;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 50%;
        animation: float 20s infinite;
    }
    
    .shape:nth-child(1) {
        width: 80px;
        height: 80px;
        left: 10%;
        animation-delay: 0s;
    }
    
    .shape:nth-child(2) {
        width: 120px;
        height: 120px;
        right: 15%;
        top: 20%;
        animation-delay: 2s;
    }
    
    .shape:nth-child(3) {
        width: 60px;
        height: 60px;
        left: 70%;
        top: 60%;
        animation-delay: 4s;
    }
    
    .requirements {
        background: #f1f5f9;
        border-radius: 10px;
        padding: 15px;
        margin-top: 20px;
        font-size: 0.85rem;
    }
    
    .requirement-item {
        display: flex;
        align-items: center;
        gap: 8px;
        color: #64748b;
        margin-bottom: 8px;
    }
    
    .requirement-item:last-child {
        margin-bottom: 0;
    }
    
    .requirement-item i {
        font-size: 0.9rem;
    }
    
    .requirement-item.valid {
        color: var(--success-color);
    }
    
    .requirement-item.valid i {
        color: var(--success-color);
    }
    
    @keyframes bounce {
        0%, 100% {
            transform: translateY(0);
        }
        50% {
            transform: translateY(-10px);
        }
    }
    
    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    @keyframes float {
        0%, 100% {
            transform: translateY(0) rotate(0deg);
        }
        50% {
            transform: translateY(-20px) rotate(180deg);
        }
    }
    
    @media (max-width: 576px) {
        .register-body {
            padding: 30px 25px;
        }
        
        .register-header {
            padding: 35px 25px;
        }
        
        .register-header h4 {
            font-size: 1.5rem;
        }
    }
</style>
</head>

<body>

<!-- Floating Shapes -->
<div class="floating-shapes">
    <div class="shape"></div>
    <div class="shape"></div>
    <div class="shape"></div>
</div>

<div class="register-container">
    <div class="register-card">
        <!-- Header -->
        <div class="register-header">
            <i class="fas fa-user-plus"></i>
            <h4>สมัครสมาชิก</h4>
            <p>สร้างบัญชีใหม่สำหรับเข้าใช้งานระบบ</p>
        </div>
        
        <!-- Body -->
        <div class="register-body">
            <?php if(isset($_GET['error'])): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <span>สมัครสมาชิกไม่สำเร็จ กรุณาลองใหม่อีกครั้ง</span>
            </div>
            <?php endif; ?>
            
            <?php if(isset($_GET['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span>สมัครสมาชิกสำเร็จ! กรุณาเข้าสู่ระบบ</span>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="register_action.php" id="registerForm">
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-user"></i> ชื่อ-นามสกุล
                    </label>
                    <div class="input-wrapper">
                        <i class="fas fa-id-badge input-icon"></i>
                        <input 
                            type="text" 
                            name="name" 
                            class="form-control" 
                            placeholder="กรอกชื่อ-นามสกุล" 
                            required
                            autofocus
                        >
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-user-circle"></i> Username
                    </label>
                    <div class="input-wrapper">
                        <i class="fas fa-at input-icon"></i>
                        <input 
                            type="text" 
                            name="username" 
                            class="form-control" 
                            placeholder="กรอก username" 
                            required
                            id="username"
                        >
                    </div>
                    <small class="text-muted" style="font-size: 0.8rem; margin-top: 5px; display: block;">
                        <i class="fas fa-info-circle"></i> ใช้สำหรับเข้าสู่ระบบ (ไม่สามารถเปลี่ยนได้ภายหลัง)
                    </small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-lock"></i> รหัสผ่าน
                    </label>
                    <div class="input-wrapper">
                        <i class="fas fa-key input-icon"></i>
                        <input 
                            type="password" 
                            name="password" 
                            class="form-control" 
                            placeholder="กรอกรหัสผ่าน" 
                            required
                            id="password"
                        >
                    </div>
                    <div class="password-strength" id="strengthIndicator">
                        <div class="strength-bar">
                            <div class="strength-bar-fill"></div>
                        </div>
                        <span class="strength-text" style="display: block; margin-top: 5px; color: #64748b;"></span>
                    </div>
                </div>
                
                <div class="requirements">
                    <div style="font-weight: 600; color: var(--dark-bg); margin-bottom: 10px;">
                        <i class="fas fa-shield-alt"></i> ข้อกำหนดรหัสผ่าน:
                    </div>
                    <div class="requirement-item" id="req-length">
                        <i class="fas fa-circle"></i>
                        <span>อย่างน้อย 6 ตัวอักษร</span>
                    </div>
                    <div class="requirement-item" id="req-letter">
                        <i class="fas fa-circle"></i>
                        <span>มีตัวอักษร a-z หรือ A-Z</span>
                    </div>
                    <div class="requirement-item" id="req-number">
                        <i class="fas fa-circle"></i>
                        <span>มีตอัวเลข 0-9</span>
                    </div>
                </div>
                
                <button type="submit" class="btn-register">
                    <i class="fas fa-user-check"></i>
                    <span>สมัครสมาชิก</span>
                </button>
            </form>
            
            <div class="login-link">
                <p style="color: #64748b; margin-bottom: 10px; font-size: 0.9rem;">
                    มีบัญชีอยู่แล้ว?
                </p>
                <a href="login.php">
                    <i class="fas fa-sign-in-alt"></i>
                    <span>เข้าสู่ระบบ</span>
                    <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>
    
    <!-- Footer Info -->
    <div style="text-align: center; margin-top: 20px; color: white; text-shadow: 0 2px 4px rgba(0,0,0,0.2);">
        <p style="margin: 0; font-size: 0.9rem; opacity: 0.9;">
            <i class="fas fa-shield-alt"></i> ข้อมูลของคุณจะถูกเก็บเป็นความลับ
        </p>
    </div>
</div>

<script>
// Password strength checker
const passwordInput = document.getElementById('password');
const strengthIndicator = document.getElementById('strengthIndicator');
const reqLength = document.getElementById('req-length');
const reqLetter = document.getElementById('req-letter');
const reqNumber = document.getElementById('req-number');

passwordInput.addEventListener('input', function() {
    const password = this.value;
    let strength = 0;
    
    // Check requirements
    const hasLength = password.length >= 6;
    const hasLetter = /[a-zA-Z]/.test(password);
    const hasNumber = /[0-9]/.test(password);
    
    // Update requirement indicators
    reqLength.classList.toggle('valid', hasLength);
    reqLetter.classList.toggle('valid', hasLetter);
    reqNumber.classList.toggle('valid', hasNumber);
    
    // Calculate strength
    if (hasLength) strength++;
    if (hasLetter) strength++;
    if (hasNumber) strength++;
    
    // Update strength indicator
    strengthIndicator.className = 'password-strength';
    const strengthText = strengthIndicator.querySelector('.strength-text');
    
    if (password.length === 0) {
        strengthText.textContent = '';
    } else if (strength === 1) {
        strengthIndicator.classList.add('strength-weak');
        strengthText.textContent = '🔴 รหัสผ่านอ่อนแอ';
        strengthText.style.color = '#ef4444';
    } else if (strength === 2) {
        strengthIndicator.classList.add('strength-medium');
        strengthText.textContent = '🟡 รหัสผ่านปานกลาง';
        strengthText.style.color = '#f59e0b';
    } else if (strength === 3) {
        strengthIndicator.classList.add('strength-strong');
        strengthText.textContent = '🟢 รหัสผ่านแข็งแรง';
        strengthText.style.color = '#10b981';
    }
});

// Form validation
document.getElementById('registerForm').addEventListener('submit', function(e) {
    const password = passwordInput.value;
    const hasLength = password.length >= 6;
    const hasLetter = /[a-zA-Z]/.test(password);
    const hasNumber = /[0-9]/.test(password);
    
    if (!hasLength || !hasLetter || !hasNumber) {
        e.preventDefault();
        alert('กรุณาตรวจสอบรหัสผ่านให้ตรงตามข้อกำหนด');
    }
});
</script>

</body>
</html>