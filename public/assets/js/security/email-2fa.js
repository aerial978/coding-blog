document.addEventListener('DOMContentLoaded', () => {
  const inputs = document.querySelectorAll('.code-digit');
  const hiddenCode = document.getElementById('code');

  if (!inputs.length || !hiddenCode) {
    return;
  }

  function getInputAt(index) {
    if (!Number.isInteger(index) || index < 0 || index >= inputs.length) {
      return null;
    }

    return inputs.item(index);
  }

  function updateCode() {
    hiddenCode.value = Array.from(inputs)
      .map((input) => input.value)
      .join('');
  }

  inputs.forEach((input, index) => {
    input.addEventListener('input', () => {
      input.value = input.value
        .replace(/\D/g, '')
        .slice(0, 1);

      const nextInput = getInputAt(index + 1);

      if (input.value && nextInput) {
        nextInput.focus();
      }

      updateCode();
    });

    input.addEventListener('keydown', (event) => {
      const previousInput = getInputAt(index - 1);

      if (event.key === 'Backspace' && !input.value && previousInput) {
        previousInput.focus();
      }
    });

    input.addEventListener('paste', (event) => {
      event.preventDefault();

      const pasted = event.clipboardData
        .getData('text')
        .replace(/\D/g, '')
        .slice(0, 6);

      pasted.split('').forEach((digit, digitIndex) => {
        const digitInput = getInputAt(digitIndex);

        if (digitInput) {
          digitInput.value = digit;
        }
      });

      updateCode();

      const nextIndex = Math.min(
        pasted.length,
        inputs.length - 1
      );

      const nextInput = getInputAt(nextIndex);

      if (nextInput) {
        nextInput.focus();
      }
    });
  });
});