// Publishes the release for a tag on this Gitea server and attaches the package.
// Called by .gitea/workflows/release.yml: title.txt and notes.txt hold the
// subject and the body of the tag's message.
const fs = require('fs');

const { API, TOKEN, TAG } = process.env;
const file = process.argv[2];
const headers = { Authorization: 'token ' + TOKEN };

(async () => {
    const created = await fetch(API + '/releases', {
        method: 'POST',
        headers: { ...headers, 'Content-Type': 'application/json' },
        body: JSON.stringify({
            tag_name: TAG,
            name: fs.readFileSync('title.txt', 'utf8').trim() || TAG,
            body: fs.readFileSync('notes.txt', 'utf8').trim(),
        }),
    });

    if (! created.ok) {
        throw new Error('Creating the release failed: ' + created.status + ' ' + await created.text());
    }

    const release = await created.json();
    const form = new FormData();
    form.append('attachment', new Blob([fs.readFileSync(file)]), file);

    const uploaded = await fetch(API + '/releases/' + release.id + '/assets?name=' + encodeURIComponent(file), {
        method: 'POST', headers, body: form,
    });

    if (! uploaded.ok) {
        throw new Error('Attaching the package failed: ' + uploaded.status + ' ' + await uploaded.text());
    }

    console.log('Released ' + release.html_url);
})().catch((error) => {
    console.error(error.message);
    process.exit(1);
});
