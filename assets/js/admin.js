/**
 * JavaScript do Admin - BuddyBoss Tutores IA
 *
 * @package BB_Tutor_IA
 * @since 1.0.0
 */

console.log("BBTutorAdmin: Script carregado!");

(function ($) {
  "use strict";

  console.log("BBTutorAdmin: jQuery wrapper executado, $ =", typeof $);

  /**
   * Objeto principal do admin.
   */
  const BBTutorAdmin = {
    /**
     * Inicializa funcionalidades do admin.
     */
    init: function () {
      this.initFileUpload();
      this.initFileDelete();
      this.initDragAndDrop();
      this.initResetStats();
    },

    /**
     * Inicializa upload de arquivos.
     */
    initFileUpload: function () {
      const fileInput = $("#bb-tutor-file-upload");

      console.log(
        "BBTutorAdmin: Inicializando upload, input encontrado:",
        fileInput.length,
      );

      if (!fileInput.length) {
        console.error("BBTutorAdmin: Input de upload não encontrado!");
        return;
      }

      // Quando o usuário seleciona um arquivo
      fileInput.on("change", function () {
        console.log("BBTutorAdmin: Arquivo selecionado, files:", this.files);

        const file = this.files[0];

        if (!file) {
          console.log("BBTutorAdmin: Nenhum arquivo selecionado");
          return;
        }

        console.log("BBTutorAdmin: Arquivo:", file.name, file.size, file.type);

        // Validar arquivo
        const validation = BBTutorAdmin.validateFile(file);
        if (!validation.valid) {
          console.error("BBTutorAdmin: Validação falhou:", validation.message);
          BBTutorAdmin.showMessage(validation.message, "error");
          fileInput.val("");
          return;
        }

        console.log("BBTutorAdmin: Validação OK, iniciando upload");

        // Mostrar nome do arquivo selecionado
        $(".bb-tutor-selected-file").text(file.name);

        // Fazer upload automaticamente
        const groupId = $(this).data("group-id");
        console.log("BBTutorAdmin: Group ID:", groupId);
        BBTutorAdmin.uploadFile(file, groupId);
      });
    },

    /**
     * Valida o arquivo antes do upload.
     */
    validateFile: function (file) {
      const maxSize = 20971520; // 20MB
      const allowedTypes = ["application/pdf", "text/plain"];
      const allowedExtensions = [".pdf", ".txt"];

      // Validar tamanho
      if (file.size > maxSize) {
        return {
          valid: false,
          message: "Arquivo muito grande. O tamanho máximo permitido é 20MB.",
        };
      }

      // Validar tipo MIME
      if (!allowedTypes.includes(file.type)) {
        // Fallback: validar por extensão
        const fileName = file.name.toLowerCase();
        const hasValidExtension = allowedExtensions.some((ext) =>
          fileName.endsWith(ext),
        );

        if (!hasValidExtension) {
          return {
            valid: false,
            message: "Tipo de arquivo não permitido. Use apenas PDF ou TXT.",
          };
        }
      }

      return { valid: true };
    },

    /**
     * Faz upload do arquivo via AJAX.
     */
    uploadFile: function (file, groupId) {
      const formData = new FormData();
      formData.append("action", "bb_tutor_upload_file");
      formData.append("nonce", $("#bb_tutor_upload_nonce").val());
      formData.append("group_id", groupId);
      formData.append("file", file);

      // Mostrar progresso
      BBTutorAdmin.showProgress(0);
      $(".bb-tutor-upload-area").addClass("uploading");

      $.ajax({
        url: bbTutorAdminData.ajaxUrl,
        type: "POST",
        data: formData,
        processData: false,
        contentType: false,
        xhr: function () {
          const xhr = new window.XMLHttpRequest();

          // Upload progress
          xhr.upload.addEventListener(
            "progress",
            function (e) {
              if (e.lengthComputable) {
                const percentComplete = (e.loaded / e.total) * 100;
                BBTutorAdmin.showProgress(percentComplete);
              }
            },
            false,
          );

          return xhr;
        },
        success: function (response) {
          if (response.success) {
            BBTutorAdmin.showMessage(
              "Arquivo enviado com sucesso! A página será recarregada.",
              "success",
            );

            // Recarregar página após 2 segundos
            setTimeout(function () {
              location.reload();
            }, 2000);
          } else {
            BBTutorAdmin.showMessage(
              response.data.message || "Erro ao enviar arquivo.",
              "error",
            );
            BBTutorAdmin.resetUpload();
          }
        },
        error: function (xhr, status, error) {
          console.error("Upload error:", error);
          BBTutorAdmin.showMessage(
            "Erro de conexão ao enviar arquivo. Tente novamente.",
            "error",
          );
          BBTutorAdmin.resetUpload();
        },
      });
    },

    /**
     * Mostra progresso do upload.
     */
    showProgress: function (percent) {
      const statusDiv = $(".bb-tutor-upload-status");
      const progressFill = $(".bb-tutor-progress-fill");
      const progressText = $(".bb-tutor-progress-text");

      statusDiv.addClass("active").show();
      progressFill.css("width", percent + "%");
      progressText.text(
        percent < 100
          ? "Enviando... " + Math.round(percent) + "%"
          : "Processando arquivo...",
      );
    },

    /**
     * Mostra mensagem de feedback.
     */
    showMessage: function (message, type) {
      const messagesDiv = $(".bb-tutor-upload-messages");
      const iconClass =
        type === "success"
          ? "dashicons-yes-alt"
          : type === "error"
            ? "dashicons-warning"
            : "dashicons-info";

      const messageHtml = `
                <div class="bb-tutor-upload-message ${type}">
                    <span class="dashicons ${iconClass}"></span>
                    <span>${message}</span>
                </div>
            `;

      messagesDiv.html(messageHtml);

      // Auto-remover mensagens de sucesso após 5 segundos
      if (type === "success") {
        setTimeout(function () {
          messagesDiv
            .find(".bb-tutor-upload-message")
            .fadeOut(300, function () {
              $(this).remove();
            });
        }, 5000);
      }
    },

    /**
     * Reseta o estado do upload.
     */
    resetUpload: function () {
      $(".bb-tutor-upload-area").removeClass("uploading");
      $(".bb-tutor-upload-status").removeClass("active").hide();
      $(".bb-tutor-progress-fill").css("width", "0%");
      $(".bb-tutor-selected-file").text("");
      $("#bb-tutor-file-upload").val("");
    },

    /**
     * Inicializa exclusão de arquivos.
     */
    initFileDelete: function () {
      $(document).on("click", ".bb-tutor-delete-file", function (e) {
        e.preventDefault();

        const fileName = $(this).data("file-name");
        const confirmMessage =
          'Tem certeza que deseja excluir o arquivo "' +
          fileName +
          '"?\n\nEsta ação não pode ser desfeita.';

        if (!confirm(confirmMessage)) {
          return;
        }

        const fileId = $(this).data("file-id");
        const groupId = $(this).data("group-id");
        const fileRow = $(this).closest("tr");
        const deleteBtn = $(this);

        // Desabilitar botão e mostrar loading
        deleteBtn
          .prop("disabled", true)
          .html(
            '<span class="dashicons dashicons-update"></span> Excluindo...',
          );
        fileRow.addClass("deleting");

        $.ajax({
          url: bbTutorAdminData.ajaxUrl,
          type: "POST",
          data: {
            action: "bb_tutor_delete_file",
            nonce: $("#bb_tutor_delete_nonce").val(),
            group_id: groupId,
            file_id: fileId,
          },
          success: function (response) {
            if (response.success) {
              // Animar remoção
              fileRow.addClass("removing");
              setTimeout(function () {
                fileRow.fadeOut(300, function () {
                  $(this).remove();

                  // Se não há mais arquivos, mostrar mensagem
                  if ($(".bb-tutor-files-table tbody tr").length === 0) {
                    $(".bb-tutor-files-table").replaceWith(
                      '<div class="bb-tutor-no-files">' +
                        '<p class="description">' +
                        '<span class="dashicons dashicons-info"></span>' +
                        "Nenhum documento enviado ainda. Envie o primeiro arquivo para começar a treinar o tutor." +
                        "</p>" +
                        "</div>",
                    );
                  }
                });
              }, 300);

              // Mostrar mensagem de sucesso
              BBTutorAdmin.showMessage(
                "Arquivo excluído com sucesso!",
                "success",
              );
            } else {
              alert(response.data.message || "Erro ao excluir arquivo.");
              deleteBtn
                .prop("disabled", false)
                .html(
                  '<span class="dashicons dashicons-trash"></span> Excluir',
                );
              fileRow.removeClass("deleting");
            }
          },
          error: function () {
            alert("Erro de conexão ao excluir arquivo. Tente novamente.");
            deleteBtn
              .prop("disabled", false)
              .html('<span class="dashicons dashicons-trash"></span> Excluir');
            fileRow.removeClass("deleting");
          },
        });
      });
    },

    /**
     * Inicializa drag and drop.
     */
    initDragAndDrop: function () {
      const uploadArea = $(".bb-tutor-upload-area");

      if (!uploadArea.length) {
        return;
      }

      // Prevenir comportamento padrão
      uploadArea.on(
        "drag dragstart dragend dragover dragenter dragleave drop",
        function (e) {
          e.preventDefault();
          e.stopPropagation();
        },
      );

      // Adicionar classe quando arrastar sobre a área
      uploadArea.on("dragover dragenter", function () {
        $(this).addClass("dragging");
      });

      uploadArea.on("dragleave dragend drop", function () {
        $(this).removeClass("dragging");
      });

      // Handle drop
      uploadArea.on("drop", function (e) {
        const files = e.originalEvent.dataTransfer.files;

        if (files.length > 0) {
          const file = files[0];
          const groupId = $("#bb-tutor-file-upload").data("group-id");

          // Validar arquivo
          const validation = BBTutorAdmin.validateFile(file);
          if (!validation.valid) {
            BBTutorAdmin.showMessage(validation.message, "error");
            return;
          }

          // Mostrar nome do arquivo
          $(".bb-tutor-selected-file").text(file.name);

          // Fazer upload
          BBTutorAdmin.uploadFile(file, groupId);
        }
      });
    },

    /**
     * Inicializa reset de estatísticas.
     */
    initResetStats: function () {
      $(document).on("click", ".bb-tutor-reset-stats", function (e) {
        e.preventDefault();

        const confirmMessage =
          "Tem certeza que deseja resetar as estatísticas de uso?\n\n" +
          "O contador de consultas será zerado e a data da última consulta será removida.\n\n" +
          "Esta ação não pode ser desfeita.";

        if (!confirm(confirmMessage)) {
          return;
        }

        const groupId = $(this).data("group-id");
        const resetBtn = $(this);

        // Desabilitar botão e mostrar loading
        resetBtn
          .prop("disabled", true)
          .html(
            '<span class="dashicons dashicons-update"></span> Resetando...',
          );

        $.ajax({
          url: bbTutorAdminData.ajaxUrl,
          type: "POST",
          data: {
            action: "bb_tutor_reset_stats",
            nonce: $("#bb_tutor_reset_stats_nonce").val(),
            group_id: groupId,
          },
          success: function (response) {
            if (response.success) {
              // Atualizar interface
              $(".bb-tutor-usage-count").text("0");
              $(".bb-tutor-last-used").text("Nenhuma consulta ainda");

              // Ocultar botão de reset
              resetBtn.closest(".bb-tutor-stats-actions").fadeOut(300);

              // Mostrar mensagem de sucesso
              BBTutorAdmin.showMessage(
                "Estatísticas resetadas com sucesso!",
                "success",
              );
            } else {
              alert(response.data.message || "Erro ao resetar estatísticas.");
              resetBtn
                .prop("disabled", false)
                .html(
                  '<span class="dashicons dashicons-update"></span> Resetar Contador',
                );
            }
          },
          error: function () {
            alert("Erro de conexão ao resetar estatísticas. Tente novamente.");
            resetBtn
              .prop("disabled", false)
              .html(
                '<span class="dashicons dashicons-update"></span> Resetar Contador',
              );
          },
        });
      });
    },
  };

  // Inicializar quando documento estiver pronto
  $(document).ready(function () {
    BBTutorAdmin.init();
  });
})(jQuery);
