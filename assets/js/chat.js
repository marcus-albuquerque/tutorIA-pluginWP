/**
 * JavaScript do Chat - BuddyBoss Tutores IA
 *
 * @package BB_Tutor_IA
 * @since 1.0.0
 */

(function () {
  "use strict";

  /**
   * Objeto principal do chat.
   */
  const BBTutorChat = {
    groupId: null,
    container: null,

    /**
     * Inicializa o chat.
     *
     * @param {number} groupId ID do grupo.
     * @param {HTMLElement} container Container do chat.
     */
    init: function (groupId, container) {
      this.groupId = groupId;
      this.container = container;
      this.render();
      this.attachEvents();
    },

    /**
     * Renderiza estrutura HTML do chat.
     */
    render: function () {
      this.container.innerHTML = `
                <div class="bb-tutor-chat">
                    <div class="bb-tutor-messages" id="bb-tutor-messages"></div>
                    <div class="bb-tutor-input">
                        <textarea 
                            id="bb-tutor-message-input" 
                            placeholder="Digite sua pergunta..."
                            rows="3"
                        ></textarea>
                        <button id="bb-tutor-send-btn" class="button">
                            ${bbTutorData.strings.send}
                        </button>
                    </div>
                </div>
            `;
    },

    /**
     * Anexa eventos aos elementos.
     */
    attachEvents: function () {
      const sendBtn = document.getElementById("bb-tutor-send-btn");
      const input = document.getElementById("bb-tutor-message-input");

      if (sendBtn) {
        sendBtn.addEventListener("click", () => this.sendMessage());
      }

      if (input) {
        input.addEventListener("keydown", (e) => {
          if (e.key === "Enter" && (e.ctrlKey || e.metaKey)) {
            e.preventDefault();
            this.sendMessage();
          }
        });
      }
    },

    /**
     * Envia mensagem para o tutor.
     */
    sendMessage: function () {
      const input = document.getElementById("bb-tutor-message-input");
      const message = input.value.trim();

      if (!message) {
        return;
      }

      // Adicionar mensagem do usuário
      this.addMessage("user", message);
      input.value = "";

      // Desabilitar botão durante envio
      const sendBtn = document.getElementById("bb-tutor-send-btn");
      sendBtn.disabled = true;
      sendBtn.textContent = bbTutorData.strings.sending;

      // Enviar via AJAX
      const formData = new FormData();
      formData.append("action", "bb_tutor_send_message");
      formData.append("nonce", bbTutorData.nonce);
      formData.append("group_id", this.groupId);
      formData.append("message", message);

      fetch(bbTutorData.ajaxUrl, {
        method: "POST",
        body: formData,
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            this.addMessage(
              "assistant",
              data.data.response,
              data.data.citations,
            );
          } else {
            this.addMessage(
              "error",
              data.data.message || bbTutorData.strings.error,
            );
          }
        })
        .catch((error) => {
          console.error("Chat error:", error);
          this.addMessage("error", bbTutorData.strings.connectionError);
        })
        .finally(() => {
          sendBtn.disabled = false;
          sendBtn.textContent = bbTutorData.strings.send;
        });
    },

    /**
     * Adiciona mensagem ao chat.
     *
     * @param {string} role Tipo da mensagem (user, assistant, error).
     * @param {string} content Conteúdo da mensagem.
     * @param {Array} citations Citações (opcional).
     */
    addMessage: function (role, content, citations) {
      const messagesDiv = document.getElementById("bb-tutor-messages");

      if (!messagesDiv) {
        return;
      }

      const messageEl = document.createElement("div");
      messageEl.className = `bb-tutor-message bb-tutor-message-${role}`;

      const contentEl = document.createElement("div");
      contentEl.className = "bb-tutor-message-content";
      contentEl.textContent = content;
      messageEl.appendChild(contentEl);

      // Adicionar citações se houver
      if (citations && Array.isArray(citations) && citations.length > 0) {
        const citationsEl = document.createElement("div");
        citationsEl.className = "bb-tutor-citations";

        const citationsTitle = document.createElement("strong");
        citationsTitle.textContent = bbTutorData.strings.sources || "Fontes:";
        citationsEl.appendChild(citationsTitle);

        const citationsList = document.createElement("ul");

        citations.forEach((citation) => {
          const listItem = document.createElement("li");

          // Extrair informações da citação
          const uri = citation.uri || "";
          const title = citation.title || citation.source || "Documento";

          // Se houver URI, criar link
          if (uri) {
            const link = document.createElement("a");
            link.href = uri;
            link.textContent = title;
            link.target = "_blank";
            link.rel = "noopener noreferrer";
            listItem.appendChild(link);
          } else {
            listItem.textContent = title;
          }

          // Adicionar informações adicionais se disponíveis
          if (
            citation.startIndex !== undefined &&
            citation.endIndex !== undefined
          ) {
            const rangeInfo = document.createElement("span");
            rangeInfo.className = "citation-range";
            rangeInfo.textContent = ` (caracteres ${citation.startIndex}-${citation.endIndex})`;
            listItem.appendChild(rangeInfo);
          }

          citationsList.appendChild(listItem);
        });

        citationsEl.appendChild(citationsList);
        messageEl.appendChild(citationsEl);
      } else if (
        role === "assistant" &&
        (!citations || citations.length === 0)
      ) {
        // Verificar se a resposta indica falta de contexto
        const lowerContent = content.toLowerCase();
        const noContextPhrases = [
          "fora do escopo",
          "não encontrei",
          "não tenho informações",
          "não está nos documentos",
          "não consta nos materiais",
        ];

        const hasNoContext = noContextPhrases.some((phrase) =>
          lowerContent.includes(phrase),
        );

        if (hasNoContext) {
          const noContextEl = document.createElement("div");
          noContextEl.className = "bb-tutor-no-context";
          noContextEl.innerHTML = `<em>${bbTutorData.strings.noContext || "Esta resposta não foi baseada nos documentos fornecidos."}</em>`;
          messageEl.appendChild(noContextEl);
        }
      }

      messagesDiv.appendChild(messageEl);
      messagesDiv.scrollTop = messagesDiv.scrollHeight;
    },
  };

  // Expor globalmente
  window.BBTutorChat = BBTutorChat;
})();
