/**
 * @file Defines InsertCults3dCardCommand, which is executed when the toolbar
 * button is pressed.
 */

import { Command } from 'ckeditor5/src/core';

export default class InsertCults3dCardCommand extends Command {
  execute() {
    const { model } = this.editor;

    const url = prompt('Paste a Cults3D model URL:');

    if (!url || !url.includes('cults3d.com')) {
      return;
    }

    // Fetch the CSRF token, then call the server-side proxy.
    const csrfUrl = Drupal.url('session/token');

    fetch(csrfUrl)
      .then((response) => response.text())
      .then((token) => {
        return fetch(Drupal.url('cults3d-embed/fetch'), {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': token,
          },
          body: JSON.stringify({ url }),
        });
      })
      .then((response) => response.json())
      .then((data) => {
        if (data.error) {
          alert('Error: ' + data.error);
          return;
        }

        model.change((writer) => {
          const cardElement = writer.createElement('cults3dCard', {
            'data-cults3d-url': data.cults3d_url || url,
            'data-cults3d-name': data.name || '',
            'data-cults3d-desc': data.description || '',
            'data-cults3d-downloads': data.download_count || 0,
            'data-cults3d-price': data.price || 'Free',
            'data-cults3d-thumb': data.thumbnail_url || '',
          });

          model.insertContent(cardElement);
        });
      })
      .catch((err) => {
        alert('Failed to fetch model data: ' + err.message);
      });
  }

  refresh() {
    const { model } = this.editor;
    const { selection } = model.document;

    const allowedIn = model.schema.findAllowedParent(
      selection.getFirstPosition(),
      'cults3dCard'
    );

    this.isEnabled = allowedIn !== null;
  }
}
