document.addEventListener('submit', (event) => {
  const form = event.target;
  if (form instanceof HTMLFormElement && form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
    event.preventDefault();
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

document.querySelectorAll('[data-autosubmit]').forEach((field) => {
  field.addEventListener('change', () => {
    const form = field.form;
    if (form) {
      form.querySelectorAll('input[name="page"]').forEach((page) => page.remove());
      form.requestSubmit ? form.requestSubmit() : form.submit();
    }
  });
});

const uploadPanel = document.getElementById('enviar');
const openUpload = () => {
  if (uploadPanel instanceof HTMLDetailsElement) {
    uploadPanel.open = true;
    window.scrollTo({ top: uploadPanel.getBoundingClientRect().top + window.scrollY - 90, behavior: 'smooth' });
  }
};
if (window.location.hash === '#enviar') {
  openUpload();
}
document.querySelectorAll('[data-open-upload]').forEach((link) => {
  link.addEventListener('click', (event) => {
    if (uploadPanel) {
      event.preventDefault();
      openUpload();
    }
  });
});

const fileInput = document.getElementById('media-files');
const fileLabel = document.querySelector('[data-file-label]');
if (fileInput instanceof HTMLInputElement && fileLabel) {
  fileInput.addEventListener('change', () => {
    const count = fileInput.files ? fileInput.files.length : 0;
    fileLabel.textContent = count === 0 ? 'Selecione um ou mais arquivos' : count === 1 ? fileInput.files[0].name : count + ' arquivos selecionados';
  });
}

document.querySelectorAll('[data-copy]').forEach((button) => {
  button.addEventListener('click', async () => {
    const input = document.querySelector(button.dataset.copy);
    if (!(input instanceof HTMLInputElement)) return;
    input.select();
    try {
      await navigator.clipboard.writeText(input.value);
    } catch {
      document.execCommand('copy');
    }
    button.textContent = 'Copiado ✓';
  });
});

document.querySelectorAll('.share-pop').forEach((pop) => {
  pop.addEventListener('toggle', () => {
    if (pop.open) {
      document.querySelectorAll('.share-pop[open]').forEach((other) => { if (other !== pop) other.open = false; });
    }
  });
});
