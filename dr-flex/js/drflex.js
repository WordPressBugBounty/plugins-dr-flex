/**
 * @version 2.0.0
 */

window.onload = function() {
    
    const numberOfTextAreas = document.getElementById("wpbody-content").querySelectorAll("textarea.drflex-textarea").length
    const numberOfCodeMirrors = document.getElementById("wpbody-content").querySelectorAll("div.CodeMirror").length

    if (numberOfCodeMirrors < numberOfTextAreas) {
        // Generate the code editor in settings
        const callback = document.getElementById("drflex_callback_textarea");
        if (callback != null && callback.type === "textarea") {
            CodeMirror.fromTextArea(callback, {
                mode:  "javascript",
                lineNumbers: true,
            });
        }

        // Generate the code editor in settings
        const examples = document.getElementsByClassName("drflex_examples_textarea");
        for (let example of examples) {
            if (example.type === "textarea") {
                CodeMirror.fromTextArea(example, {
                    mode:  "javascript",
                    lineNumbers: true,
                }).setSize("100%", null);
            }
        }
    }

    // Register spinner on update
    var el = document.getElementsByClassName("drflex-submit");
    if (el.length > 0) {
        el[0].addEventListener("click", function(event) {
            event.target.parentElement.classList.add('drflex-submitting');
        });
    }

    // Toggle the api key visibility
    const togglePassword = document.querySelector('#togglePassword');
    const api_key = document.querySelector('#drflex_api_key');

    if (togglePassword != null)
        togglePassword.addEventListener('click', function (e) {
            const type = api_key.getAttribute('type') === 'password' ? 'text' : 'password';
            api_key.setAttribute('type', type);

            this.classList.toggle('dashicons-hidden');
            this.classList.toggle('dashicons-visibility');
        });
}