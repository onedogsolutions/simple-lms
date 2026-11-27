import { useEffect, useState } from 'react';

const groups = [
  {
    title: 'Content and progress',
    fields: [
      ['course_post_type', 'Course post type', 'text'],
      ['lesson_post_type', 'Lesson post type', 'text'],
      ['relationship_mode', 'Relationship mode', 'select', ['post_parent', 'pods_field']],
      ['relationship_field', 'Pods relationship field', 'text'],
      ['relationship_storage', 'Pods relationship storage', 'select', ['meta', 'table']],
      ['relationship_direction', 'Relationship direction', 'select', ['course_to_lessons', 'lesson_to_course']],
      ['lesson_order_field', 'Lesson order field', 'text'],
      ['standalone_courses_as_lessons', 'Treat video courses as standalone lessons', 'checkbox'],
      ['completed_lessons_meta', 'Completed lessons meta key', 'text'],
      ['completed_courses_meta', 'Completed courses meta key', 'text'],
      ['verified_courses_meta', 'Verified courses meta key', 'text'],
    ],
  },
  {
    title: 'Access',
    fields: [
      ['access_method', 'Access method', 'select', ['pods', 'woocommerce', 'pmpro', 'logged_in']],
      ['pods_access_field', 'Pods enrollment field', 'text'],
      ['access_meta_key', 'Enrollment user meta key', 'text'],
      ['woocommerce_course_meta', 'WooCommerce course meta key', 'text'],
      ['pmpro_course_meta', 'PMPro course meta key', 'text'],
    ],
  },
  {
    title: 'Video tracking',
    fields: [
      ['tracking_enabled', 'Enable completion tracking', 'checkbox'],
      ['presto_event', 'Presto completion event', 'text'],
    ],
  },
  {
    title: 'Verification and certificates',
    fields: [
      ['verification_page_id', 'Verification page ID', 'number'],
      ['verification_form_id', 'Verification form ID', 'number'],
      ['course_field_id', 'Course URL field ID', 'text'],
      ['course_title_field_id', 'Course title field ID', 'text'],
      ['user_name_field_id', 'Name field ID', 'text'],
      ['user_name_first_field_id', 'First name input ID', 'text'],
      ['user_name_last_field_id', 'Last name input ID', 'text'],
      ['user_email_field_id', 'Email field ID', 'text'],
      ['state_field_id', 'State field ID', 'text'],
      ['completion_date_field_id', 'Completion date field ID', 'text'],
      ['pdf_template_id', 'Gravity PDF template post ID', 'number'],
      ['pdf_template_slug', 'Gravity PDF template slug', 'text'],
      ['account_page_id', 'Account page ID', 'number'],
    ],
  },
];

function Field({ definition, value, onChange }) {
  const [key, label, type, options] = definition;
  const id = `simple-lms-${key}`;

  if (type === 'checkbox') {
    return (
      <label htmlFor={id} className="flex items-start gap-3 rounded-xl border border-slate-200 bg-white p-4">
        <input
          id={id}
          type="checkbox"
          checked={Boolean(value)}
          onChange={(event) => onChange(key, event.target.checked)}
          className="mt-1 h-4 w-4 accent-[#011638]"
        />
        <span className="text-sm font-medium text-slate-700">{label}</span>
      </label>
    );
  }

  return (
    <label htmlFor={id} className="block space-y-2">
      <span className="text-sm font-medium text-slate-700">{label}</span>
      {type === 'select' ? (
        <select
          id={id}
          value={value ?? ''}
          onChange={(event) => onChange(key, event.target.value)}
          className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none focus:border-[#011638] focus:ring-2 focus:ring-[#b7c3f3]"
        >
          {options.map((option) => (
            <option key={option} value={option}>{option}</option>
          ))}
        </select>
      ) : (
        <input
          id={id}
          type={type}
          value={value ?? ''}
          onChange={(event) => onChange(key, type === 'number' ? Number(event.target.value) : event.target.value)}
          className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none focus:border-[#011638] focus:ring-2 focus:ring-[#b7c3f3]"
        />
      )}
    </label>
  );
}

function SystemStatus({ system }) {
  if (!system) return null;
  const rows = [
    ['WordPress', system.wordpress_version],
    ['PHP', system.php_version],
    ['SimpleLMS', system.plugin_version],
    ['Configured', system.configured ? 'Yes' : 'No'],
    ['Pods', system.pods || 'Not detected'],
    ['Presto Player', system.presto_player || 'Not detected'],
    ['Gravity Forms', system.gravity_forms || 'Not detected'],
    ['Gravity PDF', system.gravity_pdf ? 'Active' : 'Not detected'],
    ['REST namespace', system.namespace],
  ];

  return (
    <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
      <h2 className="text-lg font-semibold text-[#011638]">System status</h2>
      <dl className="mt-5 divide-y divide-slate-100">
        {rows.map(([label, value]) => (
          <div key={label} className="grid gap-1 py-3 sm:grid-cols-3">
            <dt className="text-sm font-medium text-slate-500">{label}</dt>
            <dd className="text-sm text-slate-900 sm:col-span-2">{String(value)}</dd>
          </div>
        ))}
      </dl>
    </section>
  );
}

export default function App() {
  const [activeTab, setActiveTab] = useState('general');
  const [settings, setSettings] = useState(null);
  const [system, setSystem] = useState(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [message, setMessage] = useState('');

  useEffect(() => {
    const controller = new AbortController();
    fetch(`${window.simpleLMSAdmin.restUrl}/settings`, {
      credentials: 'same-origin',
      headers: { 'X-WP-Nonce': window.simpleLMSAdmin.nonce },
      signal: controller.signal,
    })
      .then(async (response) => {
        const payload = await response.json();
        if (!response.ok) throw new Error(payload.message || 'Settings could not be loaded.');
        setSettings(payload.settings);
        setSystem(payload.system);
      })
      .catch((error) => {
        if (error.name !== 'AbortError') setMessage(error.message);
      })
      .finally(() => setLoading(false));
    return () => controller.abort();
  }, []);

  function updateField(key, value) {
    setSettings((current) => ({ ...current, [key]: value }));
    setMessage('');
  }

  async function saveSettings(event) {
    event.preventDefault();
    setSaving(true);
    setMessage('');
    try {
      const response = await fetch(`${window.simpleLMSAdmin.restUrl}/settings`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': window.simpleLMSAdmin.nonce,
        },
        body: JSON.stringify(settings),
      });
      const payload = await response.json();
      if (!response.ok) throw new Error(payload.message || 'Settings could not be saved.');
      setSettings(payload.settings);
      setSystem(payload.system);
      setMessage('Settings saved.');
    } catch (error) {
      setMessage(error.message);
    } finally {
      setSaving(false);
    }
  }

  if (loading) {
    return <div className="p-6 text-sm text-slate-500">Loading SimpleLMS…</div>;
  }

  return (
    <div className="space-y-6">
      <header className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <p className="text-xs font-semibold uppercase tracking-[0.18em] text-[#ec2991]">Lean LMS connector</p>
          <h1 className="mt-1 text-2xl font-semibold text-[#011638]">Settings</h1>
        </div>
        <div className="flex gap-2 rounded-xl bg-white p-1 shadow-sm ring-1 ring-slate-200">
          <button
            type="button"
            onClick={() => setActiveTab('general')}
            aria-pressed={activeTab === 'general'}
            className={`rounded-lg px-4 py-2 text-sm font-medium ${activeTab === 'general' ? 'bg-[#011638] text-[#b7c3f3]' : 'text-slate-600 hover:bg-slate-50'}`}
          >
            General Settings
          </button>
          <button
            type="button"
            onClick={() => setActiveTab('status')}
            aria-pressed={activeTab === 'status'}
            className={`rounded-lg px-4 py-2 text-sm font-medium ${activeTab === 'status' ? 'bg-[#011638] text-[#b7c3f3]' : 'text-slate-600 hover:bg-slate-50'}`}
          >
            System Status
          </button>
        </div>
      </header>

      {message && (
        <div role="status" className="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700">
          {message}
        </div>
      )}

      {activeTab === 'status' ? (
        <SystemStatus system={system} />
      ) : settings ? (
        <form onSubmit={saveSettings} className="space-y-6">
          {groups.map((group) => (
            <section key={group.title} className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
              <h2 className="text-lg font-semibold text-[#011638]">{group.title}</h2>
              <div className="mt-5 grid gap-5 md:grid-cols-2">
                {group.fields.map((definition) => (
                  <Field
                    key={definition[0]}
                    definition={definition}
                    value={settings[definition[0]]}
                    onChange={updateField}
                  />
                ))}
              </div>
            </section>
          ))}
          <div className="flex items-center gap-4">
            <button
              type="submit"
              disabled={saving}
              className="rounded-lg bg-[#011638] px-5 py-2.5 text-sm font-semibold text-white disabled:opacity-60"
            >
              {saving ? 'Saving…' : 'Save Settings'}
            </button>
            <span className="text-xs text-slate-500">Stored in simple_lms_settings</span>
          </div>
        </form>
      ) : (
        <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
          Settings are unavailable. Reload the page to try again.
        </div>
      )}
    </div>
  );
}
