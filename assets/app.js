document.addEventListener('submit', (event) => {
  const form = event.target;
  if (form instanceof HTMLFormElement && form.dataset.confirm) {
    if (!window.confirm(form.dataset.confirm)) {
      event.preventDefault();
    }
  }
});

document.querySelectorAll('[data-select-on-click]').forEach((input) => {
  input.addEventListener('click', () => input.select());
});

document.querySelectorAll('[data-history-back]').forEach((link) => {
  link.addEventListener('click', (event) => {
    event.preventDefault();
    window.history.back();
  });
});
