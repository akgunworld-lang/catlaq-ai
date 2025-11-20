(function () {
  const config = window.catlaqEngagementConfig || {};
  const rest = config.rest || {};
  const permissions = config.permissions || {};
  const i18n = Object.assign(
    {
      postPlaceholder: 'Write an update...',
      postButton: 'Share update',
      loginPrompt: 'Please sign in to publish updates.',
      emptyState: 'No activity yet. Start the conversation!',
      refresh: 'Refresh',
      visibilityLabel: 'Audience'
    },
    config.i18n || {}
  );

  const restRoot = (rest.root || '/wp-json/catlaq/v1').replace(/\/$/, '');
  const nonce = rest.nonce || '';
  const canPost = !!permissions.can_post;
  const blockedMessage = permissions.blocked_message || '';
  const visibilityOptions = Array.isArray(permissions.visibility) && permissions.visibility.length
    ? permissions.visibility
    : ['public'];
  const containers = document.querySelectorAll('[data-component="engagement-feed"]');

  if (!containers.length) {
    return;
  }

  containers.forEach(function (container) {
    const state = {
      posts: [],
      loading: false,
      error: ''
    };

    function render() {
      const fragments = [];
      fragments.push(canPost ? renderComposer() : renderGuard());

      if (state.error) {
        fragments.push('<div class="catlaq-engagement-error">' + escapeHtml(state.error) + '</div>');
      }

      fragments.push(
        '<div class="catlaq-engagement-toolbar">' +
          '<button type="button" data-action="refresh-feed">' + i18n.refresh + '</button>' +
          (state.loading ? '<span class="catlaq-engagement-loading">...</span>' : '') +
        '</div>'
      );

      fragments.push(renderPosts());
      container.innerHTML = fragments.join('');
    }

    function renderGuard() {
      const message = blockedMessage || i18n.loginPrompt;
      return '<p class="catlaq-engagement-login">' + escapeHtml(message) + '</p>';
    }

    function renderComposer() {
      const selectField = visibilityOptions.length > 1
        ? '<label class="catlaq-engagement-form__audience">' +
            '<span>' + i18n.visibilityLabel + '</span>' +
            buildSelectHTML(visibilityOptions) +
          '</label>'
        : '<input type="hidden" name="engagement_visibility" value="' + escapeHtml(visibilityOptions[0]) + '">';

      return (
        '<form class="catlaq-engagement-form">' +
          '<textarea name="engagement_content" rows="3" placeholder="' + i18n.postPlaceholder + '" required></textarea>' +
          selectField +
          '<div class="catlaq-engagement-form__actions">' +
            '<button type="submit">' + i18n.postButton + '</button>' +
          '</div>' +
        '</form>'
      );
    }

    function buildSelectHTML(options) {
      return (
        '<select name="engagement_visibility">' +
          options
            .map(function (value) {
              const safeValue = escapeHtml(value);
              return '<option value="' + safeValue + '">' + safeValue + '</option>';
            })
            .join('') +
        '</select>'
      );
    }

    function renderPosts() {
      if (state.loading && !state.posts.length) {
        return '<p class="catlaq-engagement-empty">Loading...</p>';
      }

      if (!state.posts.length) {
        return '<p class="catlaq-engagement-empty">' + i18n.emptyState + '</p>';
      }

      const items = state.posts
        .map(function (post) {
          const authorName = post && post.author && post.author.display ? post.author.display : 'Anonymous';
          return (
            '<li class="catlaq-engagement-item">' +
              '<div class="catlaq-engagement-item__meta">' +
                '<strong>' + escapeHtml(authorName) + '</strong>' +
                '<span>' + formatDate(post && post.created_at) + '</span>' +
              '</div>' +
              '<div class="catlaq-engagement-item__body">' + sanitizeInline(post && post.content) + '</div>' +
            '</li>'
          );
        })
        .join('');

      return '<ul class="catlaq-engagement-list">' + items + '</ul>';
    }

    function escapeHtml(value) {
      const div = document.createElement('div');
      div.textContent = value || '';
      return div.innerHTML;
    }

    function sanitizeInline(value) {
      const div = document.createElement('div');
      div.innerHTML = value || '';
      Array.prototype.slice.call(div.querySelectorAll('script')).forEach(function (node) {
        node.parentNode.removeChild(node);
      });
      return div.innerHTML;
    }

    function formatDate(value) {
      if (!value) {
        return '';
      }
      try {
        return new Date(value).toLocaleString();
      } catch (error) {
        return value;
      }
    }

    function buildHeaders() {
      const headers = { 'Content-Type': 'application/json' };
      if (nonce) {
        headers['X-WP-Nonce'] = nonce;
      }
      return headers;
    }

    function fetchFeed() {
      state.loading = true;
      state.error = '';
      render();

      fetch(restRoot + '/engagement/feed')
        .then(function (response) {
          if (!response.ok) {
            throw new Error('Feed load failed');
          }
          return response.json();
        })
        .then(function (data) {
          state.posts = Array.isArray(data) ? data : [];
        })
        .catch(function (error) {
          console.error(error);
          state.error = 'Unable to load activity.';
        })
        .finally(function () {
          state.loading = false;
          render();
        });
    }

    function submitPost(content, visibility) {
      state.error = '';
      state.loading = true;
      render();

      fetch(restRoot + '/engagement/feed', {
        method: 'POST',
        headers: buildHeaders(),
        body: JSON.stringify({ content: content, visibility: visibility })
      })
        .then(function (response) {
          if (!response.ok) {
            return response.json().then(function (payload) {
              throw new Error((payload && payload.message) || 'Post failed');
            });
          }
          return response.json();
        })
        .then(function (data) {
          state.posts = Array.isArray(data) ? data : state.posts;
        })
        .catch(function (error) {
          console.error(error);
          state.error = error.message || 'Unknown error.';
        })
        .finally(function () {
          state.loading = false;
          render();
        });
    }

    container.addEventListener('submit', function (event) {
      if (!event.target.classList.contains('catlaq-engagement-form')) {
        return;
      }
      event.preventDefault();
      const textarea = event.target.querySelector('textarea[name="engagement_content"]');
      const content = textarea ? textarea.value.trim() : '';
      if (!content) {
        return;
      }
      const visibilityField = event.target.querySelector('[name="engagement_visibility"]');
      const visibility = visibilityField ? visibilityField.value : visibilityOptions[0];
      submitPost(content, visibility);
      textarea.value = '';
    });

    container.addEventListener('click', function (event) {
      if (event.target.matches('[data-action="refresh-feed"]')) {
        event.preventDefault();
        fetchFeed();
      }
    });

    fetchFeed();
  });
})();


