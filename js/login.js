// Frontend interactions only. Secure authentication will be added through the backend.
const form = document.getElementById('login-form');
const emailInput = document.getElementById('email');
const passwordInput = document.getElementById('password');
const emailError = document.getElementById('email-error');
const passwordError = document.getElementById('password-error');
const formMessage = document.getElementById('form-message');
const togglePassword = document.getElementById('toggle-password');
const rememberInput = document.getElementById('remember');

function showMessage(message) {
  formMessage.textContent = message;
  formMessage.classList.add('visible');
}
function clearMessage() {
  formMessage.textContent = '';
  formMessage.classList.remove('visible');
}
function setError(input, element, message) {
  element.textContent = message;
  input.setAttribute('aria-invalid', message ? 'true' : 'false');
}
function validEmail(value) {
  return /^[^\\s@]+@[^\\s@]+\\.[^\\s@]+$/.test(value);
}

try {
  const rememberedEmail = localStorage.getItem('fleetRememberedEmail');
  if (rememberedEmail) {
    emailInput.value = rememberedEmail;
    rememberInput.checked = true;
  }
} catch (_) {}

togglePassword.addEventListener('click', () => {
  const showing = passwordInput.type === 'text';
  passwordInput.type = showing ? 'password' : 'text';
  togglePassword.textContent = showing ? 'Show password' : 'Hide password';
  togglePassword.setAttribute('aria-pressed', String(!showing));
});
emailInput.addEventListener('input', () => {
  setError(emailInput, emailError, '');
  clearMessage();
});
passwordInput.addEventListener('input', () => {
  setError(passwordInput, passwordError, '');
  clearMessage();
});

form.addEventListener('submit', (event) => {
  event.preventDefault();
  clearMessage();
  const email = emailInput.value.trim();
  const password = passwordInput.value;
  let valid = true;

  if (!email) {
    setError(emailInput, emailError, 'Enter your email address.');
    valid = false;
  } else if (!validEmail(email)) {
    setError(emailInput, emailError, 'Enter a valid email address.');
    valid = false;
  } else {
    setError(emailInput, emailError, '');
  }
  if (!password) {
    setError(passwordInput, passwordError, 'Enter your password.');
    valid = false;
  } else {
    setError(passwordInput, passwordError, '');
  }
  if (!valid) {
    showMessage('Check the highlighted fields and try again.');
    return;
  }

  try {
    if (rememberInput.checked) localStorage.setItem('fleetRememberedEmail', email);
    else localStorage.removeItem('fleetRememberedEmail');
  } catch (_) {}

  // Do not pretend to authenticate before a secure backend exists.
  showMessage('The login interface is ready, but account verification is not connected yet. Next we will add secure backend verification, sessions and role-based dashboard access.');
});
