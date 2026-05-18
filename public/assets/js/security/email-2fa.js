document.addEventListener('DOMContentLoaded', () => {
    const inputs = Array.from(document.querySelectorAll('.code-digit'));
    const hiddenCode = document.getElementById('code');

    if (!inputs.length || !hiddenCode) {
        return;
    }

    function updateCode() {
        hiddenCode.value = inputs
            .map((input) => input.value)
            .join('');
    }

    inputs.forEach((input, index) => {
        input.addEventListener('input', () => {
            input.value = input.value
                .replace(/\D/g, '')
                .slice(0, 1);

            if (input.value && inputs[index + 1]) {
                inputs[index + 1].focus();
            }

            updateCode();
        });

        input.addEventListener('keydown', (event) => {
            if (
                event.key === 'Backspace' &&
                !input.value &&
                inputs[index - 1]
            ) {
                inputs[index - 1].focus();
            }
        });

        input.addEventListener('paste', (event) => {
            event.preventDefault();

            const pasted = event.clipboardData
                .getData('text')
                .replace(/\D/g, '')
                .slice(0, 6);

            pasted.split('').forEach((digit, digitIndex) => {
                if (inputs[digitIndex]) {
                    inputs[digitIndex].value = digit;
                }
            });

            updateCode();

            const nextIndex = Math.min(
                pasted.length,
                inputs.length - 1
            );

            inputs[nextIndex].focus();
        });
    });
});