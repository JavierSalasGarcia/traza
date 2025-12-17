<?php
/**
 * Componente de comentarios reutilizable
 *
 * Variables requeridas:
 * - $referencia_tipo: 'aviso' o 'propuesta'
 * - $referencia_id: ID del aviso o propuesta
 * - $user_id: ID del usuario actual
 */

if (!isset($referencia_tipo) || !isset($referencia_id) || !isset($user_id)) {
    return;
}

$comentario_model = new Comentario();
$comentarios = $comentario_model->getByReference($referencia_tipo, $referencia_id);
$total_comentarios = count($comentarios);

// Procesar formulario de comentario
if (is_post() && isset($_POST['submit_comment'])) {
    if (verify_csrf_token(input('csrf_token'))) {
        $contenido = trim(input('contenido'));
        $comentario_padre_id = input('comentario_padre_id') ?: null;
        $es_anonimo = isset($_POST['es_anonimo']) && $_POST['es_anonimo'] === '1';

        if (!empty($contenido)) {
            $result = $comentario_model->create([
                'contenido' => $contenido,
                'usuario_id' => $user_id,
                'referencia_tipo' => $referencia_tipo,
                'referencia_id' => $referencia_id,
                'es_anonimo' => $es_anonimo,
                'comentario_padre_id' => $comentario_padre_id
            ]);

            if ($result['success']) {
                set_flash('success', $result['message']);
                redirect($_SERVER['REQUEST_URI']);
            } else {
                set_flash('error', $result['message']);
            }
        }
    }
}

// Procesar like/unlike
if (is_post() && isset($_POST['toggle_like'])) {
    if (verify_csrf_token(input('csrf_token'))) {
        $comentario_id = (int) input('comentario_id');

        if ($comentario_model->hasLiked($comentario_id, $user_id)) {
            $result = $comentario_model->unlike($comentario_id, $user_id);
        } else {
            $result = $comentario_model->like($comentario_id, $user_id);
        }

        echo json_encode($result);
        exit;
    }
}
?>

<div class="comments-section" id="comentarios">
    <div class="comments-header">
        <h3>
            <i class="fas fa-comments"></i>
            Comentarios (<?= $total_comentarios ?>)
        </h3>
    </div>

    <!-- Formulario de nuevo comentario -->
    <div class="comment-form-container">
        <form method="POST" class="comment-form" id="mainCommentForm">
            <?= csrf_field() ?>
            <input type="hidden" name="submit_comment" value="1">

            <div class="comment-input-wrapper">
                <textarea name="contenido"
                          class="comment-textarea"
                          placeholder="Escribe tu comentario..."
                          rows="3"
                          required></textarea>
            </div>

            <div class="comment-form-actions">
                <div class="form-check">
                    <input type="checkbox" id="es_anonimo_main" name="es_anonimo" value="1">
                    <label for="es_anonimo_main">
                        <i class="fas fa-user-secret"></i>
                        Comentar como anónimo
                    </label>
                </div>
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fas fa-paper-plane"></i>
                    Publicar
                </button>
            </div>
        </form>
    </div>

    <!-- Lista de comentarios -->
    <?php if (empty($comentarios)): ?>
        <div class="empty-comments">
            <i class="fas fa-comments"></i>
            <p>Aún no hay comentarios. ¡Sé el primero en comentar!</p>
        </div>
    <?php else: ?>
        <div class="comments-list">
            <?php foreach ($comentarios as $comentario): ?>
                <?php
                $respuestas = $comentario_model->getReplies($comentario['id']);
                $user_liked = $comentario_model->hasLiked($comentario['id'], $user_id);
                $can_edit = ($comentario['usuario_id'] == $user_id && !$comentario['es_anonimo']);
                $can_delete = ($comentario['usuario_id'] == $user_id || is_admin());
                ?>

                <div class="comment-item" id="comment-<?= $comentario['id'] ?>">
                    <div class="comment-avatar">
                        <?php if ($comentario['usuario_imagen'] && !$comentario['es_anonimo']): ?>
                            <img src="<?= upload_url($comentario['usuario_imagen']) ?>"
                                 alt="<?= sanitize($comentario['usuario_nombre']) ?>">
                        <?php else: ?>
                            <i class="fas fa-<?= $comentario['es_anonimo'] ? 'user-secret' : 'user-circle' ?>"></i>
                        <?php endif; ?>
                    </div>

                    <div class="comment-content">
                        <div class="comment-header">
                            <div class="comment-author">
                                <strong><?= sanitize($comentario['usuario_nombre']) ?></strong>
                                <?php if ($comentario['es_anonimo']): ?>
                                    <span class="anon-badge">
                                        <i class="fas fa-user-secret"></i>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="comment-meta">
                                <span><?= time_ago($comentario['fecha_creacion']) ?></span>
                                <?php if ($comentario['editado']): ?>
                                    <span class="edited-badge" title="Editado el <?= format_date($comentario['fecha_edicion']) ?>">
                                        <i class="fas fa-pen"></i> editado
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="comment-text">
                            <?= nl2br(sanitize($comentario['contenido'])) ?>
                        </div>

                        <div class="comment-actions">
                            <button class="comment-action like-btn <?= $user_liked ? 'liked' : '' ?>"
                                    data-comment-id="<?= $comentario['id'] ?>"
                                    onclick="toggleLike(<?= $comentario['id'] ?>)">
                                <i class="fas fa-heart"></i>
                                <span class="like-count"><?= $comentario['total_likes'] ?></span>
                            </button>

                            <button class="comment-action reply-btn"
                                    onclick="showReplyForm(<?= $comentario['id'] ?>)">
                                <i class="fas fa-reply"></i>
                                Responder
                            </button>

                            <?php if ($can_delete): ?>
                                <button class="comment-action delete-btn"
                                        onclick="deleteComment(<?= $comentario['id'] ?>)">
                                    <i class="fas fa-trash"></i>
                                    Eliminar
                                </button>
                            <?php endif; ?>

                            <button class="comment-action report-btn"
                                    onclick="reportComment(<?= $comentario['id'] ?>)">
                                <i class="fas fa-flag"></i>
                                Reportar
                            </button>
                        </div>

                        <!-- Formulario de respuesta (oculto por defecto) -->
                        <div class="reply-form-container" id="replyForm-<?= $comentario['id'] ?>" style="display: none;">
                            <form method="POST" class="comment-form reply-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="submit_comment" value="1">
                                <input type="hidden" name="comentario_padre_id" value="<?= $comentario['id'] ?>">

                                <textarea name="contenido"
                                          class="comment-textarea"
                                          placeholder="Escribe tu respuesta..."
                                          rows="2"
                                          required></textarea>

                                <div class="comment-form-actions">
                                    <div class="form-check">
                                        <input type="checkbox" id="es_anonimo_<?= $comentario['id'] ?>" name="es_anonimo" value="1">
                                        <label for="es_anonimo_<?= $comentario['id'] ?>">
                                            <i class="fas fa-user-secret"></i>
                                            Anónimo
                                        </label>
                                    </div>
                                    <div class="reply-actions">
                                        <button type="button" class="btn btn-outline btn-sm"
                                                onclick="hideReplyForm(<?= $comentario['id'] ?>)">
                                            Cancelar
                                        </button>
                                        <button type="submit" class="btn btn-primary btn-sm">
                                            <i class="fas fa-reply"></i>
                                            Responder
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <!-- Respuestas -->
                        <?php if (!empty($respuestas)): ?>
                            <div class="replies-container">
                                <?php foreach ($respuestas as $respuesta): ?>
                                    <?php
                                    $user_liked_reply = $comentario_model->hasLiked($respuesta['id'], $user_id);
                                    $can_delete_reply = ($respuesta['usuario_id'] == $user_id || is_admin());
                                    ?>

                                    <div class="comment-item reply-item" id="comment-<?= $respuesta['id'] ?>">
                                        <div class="comment-avatar">
                                            <?php if ($respuesta['usuario_imagen'] && !$respuesta['es_anonimo']): ?>
                                                <img src="<?= upload_url($respuesta['usuario_imagen']) ?>"
                                                     alt="<?= sanitize($respuesta['usuario_nombre']) ?>">
                                            <?php else: ?>
                                                <i class="fas fa-<?= $respuesta['es_anonimo'] ? 'user-secret' : 'user-circle' ?>"></i>
                                            <?php endif; ?>
                                        </div>

                                        <div class="comment-content">
                                            <div class="comment-header">
                                                <div class="comment-author">
                                                    <strong><?= sanitize($respuesta['usuario_nombre']) ?></strong>
                                                    <?php if ($respuesta['es_anonimo']): ?>
                                                        <span class="anon-badge">
                                                            <i class="fas fa-user-secret"></i>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="comment-meta">
                                                    <span><?= time_ago($respuesta['fecha_creacion']) ?></span>
                                                    <?php if ($respuesta['editado']): ?>
                                                        <span class="edited-badge">
                                                            <i class="fas fa-pen"></i> editado
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <div class="comment-text">
                                                <?= nl2br(sanitize($respuesta['contenido'])) ?>
                                            </div>

                                            <div class="comment-actions">
                                                <button class="comment-action like-btn <?= $user_liked_reply ? 'liked' : '' ?>"
                                                        data-comment-id="<?= $respuesta['id'] ?>"
                                                        onclick="toggleLike(<?= $respuesta['id'] ?>)">
                                                    <i class="fas fa-heart"></i>
                                                    <span class="like-count"><?= $respuesta['total_likes'] ?></span>
                                                </button>

                                                <?php if ($can_delete_reply): ?>
                                                    <button class="comment-action delete-btn"
                                                            onclick="deleteComment(<?= $respuesta['id'] ?>)">
                                                        <i class="fas fa-trash"></i>
                                                        Eliminar
                                                    </button>
                                                <?php endif; ?>

                                                <button class="comment-action report-btn"
                                                        onclick="reportComment(<?= $respuesta['id'] ?>)">
                                                    <i class="fas fa-flag"></i>
                                                    Reportar
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
.comments-section {
    margin-top: var(--space-8);
}

.comments-header {
    margin-bottom: var(--space-6);
    padding-bottom: var(--space-4);
    border-bottom: 2px solid rgba(255, 255, 255, 0.1);
}

.comments-header h3 {
    display: flex;
    align-items: center;
    gap: var(--space-3);
    font-size: var(--font-size-2xl);
}

.comment-form-container {
    background: var(--color-gray-900);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: var(--radius-xl);
    padding: var(--space-6);
    margin-bottom: var(--space-6);
}

.comment-form {
    display: flex;
    flex-direction: column;
    gap: var(--space-4);
}

.comment-textarea {
    width: 100%;
    padding: var(--space-4);
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: var(--radius-lg);
    color: var(--color-white);
    font-family: inherit;
    font-size: var(--font-size-base);
    line-height: var(--line-height-relaxed);
    resize: vertical;
    transition: all var(--transition-fast);
}

.comment-textarea:focus {
    outline: none;
    border-color: var(--color-primary);
    background: rgba(255, 255, 255, 0.08);
}

.comment-form-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.form-check {
    display: flex;
    align-items: center;
    gap: var(--space-2);
}

.form-check input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.form-check label {
    display: flex;
    align-items: center;
    gap: var(--space-2);
    color: var(--color-gray-400);
    font-size: var(--font-size-sm);
    cursor: pointer;
    margin: 0;
}

.empty-comments {
    text-align: center;
    padding: var(--space-12);
    background: var(--color-gray-900);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: var(--radius-xl);
}

.empty-comments i {
    font-size: var(--font-size-5xl);
    color: var(--color-gray-600);
    margin-bottom: var(--space-4);
}

.empty-comments p {
    color: var(--color-gray-400);
    margin: 0;
}

.comments-list {
    display: flex;
    flex-direction: column;
    gap: var(--space-4);
}

.comment-item {
    display: flex;
    gap: var(--space-4);
    background: var(--color-gray-900);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: var(--radius-xl);
    padding: var(--space-5);
}

.reply-item {
    background: rgba(255, 255, 255, 0.03);
}

.comment-avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    overflow: hidden;
    background: var(--color-gray-800);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.comment-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.comment-avatar i {
    font-size: var(--font-size-2xl);
    color: var(--color-gray-600);
}

.comment-content {
    flex: 1;
    min-width: 0;
}

.comment-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--space-2);
}

.comment-author {
    display: flex;
    align-items: center;
    gap: var(--space-2);
}

.anon-badge {
    display: inline-flex;
    align-items: center;
    padding: var(--space-1) var(--space-2);
    background: rgba(255, 170, 0, 0.2);
    color: var(--color-warning);
    border-radius: var(--radius-sm);
    font-size: var(--font-size-xs);
}

.comment-meta {
    display: flex;
    align-items: center;
    gap: var(--space-2);
    font-size: var(--font-size-sm);
    color: var(--color-gray-400);
}

.edited-badge {
    display: inline-flex;
    align-items: center;
    gap: var(--space-1);
    font-style: italic;
}

.comment-text {
    margin-bottom: var(--space-3);
    line-height: var(--line-height-relaxed);
    color: var(--color-gray-300);
}

.comment-actions {
    display: flex;
    gap: var(--space-4);
    flex-wrap: wrap;
}

.comment-action {
    display: flex;
    align-items: center;
    gap: var(--space-2);
    padding: var(--space-2) var(--space-3);
    background: transparent;
    border: none;
    color: var(--color-gray-400);
    font-size: var(--font-size-sm);
    cursor: pointer;
    transition: all var(--transition-fast);
    border-radius: var(--radius-md);
}

.comment-action:hover {
    background: rgba(255, 255, 255, 0.05);
    color: var(--color-white);
}

.like-btn.liked {
    color: var(--color-error);
}

.like-btn.liked i {
    animation: heartBeat 0.3s ease;
}

@keyframes heartBeat {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.2); }
}

.delete-btn:hover {
    color: var(--color-error);
}

.report-btn:hover {
    color: var(--color-warning);
}

.reply-form-container {
    margin-top: var(--space-4);
    padding: var(--space-4);
    background: rgba(255, 255, 255, 0.03);
    border-radius: var(--radius-lg);
}

.reply-actions {
    display: flex;
    gap: var(--space-2);
}

.replies-container {
    margin-top: var(--space-4);
    padding-left: var(--space-6);
    border-left: 2px solid rgba(0, 153, 255, 0.3);
    display: flex;
    flex-direction: column;
    gap: var(--space-3);
}

@media (max-width: 768px) {
    .comment-item {
        flex-direction: column;
    }

    .comment-avatar {
        width: 40px;
        height: 40px;
    }

    .comment-actions {
        gap: var(--space-2);
    }

    .replies-container {
        padding-left: var(--space-4);
    }
}
</style>

<script>
function toggleLike(comentarioId) {
    const btn = document.querySelector(`.like-btn[data-comment-id="${comentarioId}"]`);
    const formData = new FormData();
    formData.append('toggle_like', '1');
    formData.append('comentario_id', comentarioId);
    formData.append('csrf_token', '<?= csrf_token() ?>');

    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            btn.classList.toggle('liked');
            btn.querySelector('.like-count').textContent = data.total_likes;
        }
    })
    .catch(error => console.error('Error:', error));
}

function showReplyForm(comentarioId) {
    const form = document.getElementById('replyForm-' + comentarioId);
    form.style.display = 'block';
    form.querySelector('textarea').focus();
}

function hideReplyForm(comentarioId) {
    const form = document.getElementById('replyForm-' + comentarioId);
    form.style.display = 'none';
    form.querySelector('textarea').value = '';
}

function deleteComment(comentarioId) {
    if (!confirm('¿Estás seguro de que quieres eliminar este comentario?')) {
        return;
    }

    const formData = new FormData();
    formData.append('delete_comment', '1');
    formData.append('comentario_id', comentarioId);
    formData.append('csrf_token', '<?= csrf_token() ?>');

    fetch('<?= base_url('public/api/comments.php') ?>', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('comment-' + comentarioId).remove();
            alert(data.message);
        } else {
            alert(data.message);
        }
    })
    .catch(error => console.error('Error:', error));
}

function reportComment(comentarioId) {
    const razon = prompt('¿Por qué reportas este comentario?');

    if (!razon) {
        return;
    }

    const formData = new FormData();
    formData.append('report_comment', '1');
    formData.append('comentario_id', comentarioId);
    formData.append('razon', razon);
    formData.append('csrf_token', '<?= csrf_token() ?>');

    fetch('<?= base_url('public/api/comments.php') ?>', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        alert(data.message);
        if (data.success) {
            location.reload();
        }
    })
    .catch(error => console.error('Error:', error));
}
</script>
