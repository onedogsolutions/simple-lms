/**
 * Settings – SimpleLMS site-wide configuration screen.
 *
 * Renders under the SimpleLMS → Settings menu. Reads and writes the
 * `slms_settings` option via the /simple-lms/v1/settings REST route, and
 * exposes the progress-table backfill Tool.
 *
 * @package
 */

import { useState, useEffect, useCallback } from '@wordpress/element';
import {
	Panel,
	PanelBody,
	SelectControl,
	TextControl,
	Button,
	Spinner,
	Notice,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

/**
 * Settings component.
 *
 * @return {JSX.Element} The rendered settings screen.
 */
const Settings = () => {
	const [settings, setSettings] = useState(null);
	const [pages, setPages] = useState([]);
	const [loading, setLoading] = useState(true);
	const [saving, setSaving] = useState(false);
	const [notice, setNotice] = useState(null);
	const [backfilling, setBackfilling] = useState(false);

	// ── Load ──────────────────────────────────────────────────────
	useEffect(() => {
		const load = async () => {
			try {
				const [settingsRes, pagesRes] = await Promise.all([
					apiFetch({ path: '/simple-lms/v1/settings' }),
					apiFetch({
						path: '/wp/v2/pages?per_page=100&_fields=id,title&orderby=title&order=asc',
					}).catch(() => []),
				]);
				setSettings(settingsRes);
				setPages(
					(pagesRes || []).map((p) => ({
						id: p.id,
						title: p.title?.rendered || `#${p.id}`,
					}))
				);
			} catch (err) {
				setNotice({ status: 'error', message: err.message });
			} finally {
				setLoading(false);
			}
		};
		load();
	}, []);

	const update = (key, value) => {
		setSettings((prev) => ({ ...prev, [key]: value }));
	};

	// ── Save ──────────────────────────────────────────────────────
	const handleSave = useCallback(async () => {
		setSaving(true);
		setNotice(null);
		try {
			const res = await apiFetch({
				path: '/simple-lms/v1/settings',
				method: 'POST',
				data: settings,
			});
			setSettings(res.settings);
			setNotice({
				status: 'success',
				message: __('Settings saved.', 'simple-lms-bridge'),
			});
		} catch (err) {
			setNotice({ status: 'error', message: err.message });
		} finally {
			setSaving(false);
		}
	}, [settings]);

	// ── Backfill Tool ─────────────────────────────────────────────
	const handleBackfill = useCallback(async () => {
		setBackfilling(true);
		setNotice(null);
		try {
			const res = await apiFetch({
				path: '/simple-lms/v1/progress/backfill',
				method: 'POST',
				data: {},
			});
			setNotice({
				status: 'success',
				message: __(
					'Backfill complete. ',
					'simple-lms-bridge'
				)
					.concat(
						`${res.rows} rows across ${res.users} user(s). `
					)
					.concat(
						`Table now holds ${res.total_rows} row(s).`
					),
			});
		} catch (err) {
			setNotice({ status: 'error', message: err.message });
		} finally {
			setBackfilling(false);
		}
	}, []);

	if (loading || !settings) {
		return <Spinner />;
	}

	const pageOptions = (emptyLabel) => [
		{ label: emptyLabel, value: 0 },
		...pages.map((p) => ({ label: p.title, value: p.id })),
	];

	return (
		<div className="slms-settings">
			<h1>{__('SimpleLMS Settings', 'simple-lms-bridge')}</h1>

			{notice && (
				<Notice
					status={notice.status}
					isDismissible
					onDismiss={() => setNotice(null)}
				>
					{notice.message}
				</Notice>
			)}

			<Panel>
				<PanelBody
					title={__('Access', 'simple-lms-bridge')}
					initialOpen={true}
				>
					<SelectControl
						label={__(
							'Default Guard Mode',
							'simple-lms-bridge'
						)}
						help={__(
							'Applied to courses that do not set their own guard mode.',
							'simple-lms-bridge'
						)}
						value={settings.default_guard_mode}
						options={[
							{
								label: __('Public', 'simple-lms-bridge'),
								value: 'public',
							},
							{
								label: __(
									'Enrolled',
									'simple-lms-bridge'
								),
								value: 'enrolled',
							},
							{
								label: __(
									'Membership level',
									'simple-lms-bridge'
								),
								value: 'level',
							},
						]}
						onChange={(val) =>
							update('default_guard_mode', val)
						}
					/>

					<SelectControl
						label={__(
							'Login Redirect Behavior',
							'simple-lms-bridge'
						)}
						help={__(
							'Where to send a logged-out visitor who hits guarded content.',
							'simple-lms-bridge'
						)}
						value={settings.login_redirect}
						options={[
							{
								label: __(
									'WordPress login',
									'simple-lms-bridge'
								),
								value: 'login',
							},
							{
								label: __(
									'PMPro checkout',
									'simple-lms-bridge'
								),
								value: 'checkout',
							},
						]}
						onChange={(val) =>
							update('login_redirect', val)
						}
					/>

					<SelectControl
						label={__(
							'Checkout Page',
							'simple-lms-bridge'
						)}
						help={__(
							'Default page for denied users (overridable per course).',
							'simple-lms-bridge'
						)}
						value={settings.checkout_page_id}
						options={pageOptions(
							__(
								'— PMPro checkout —',
								'simple-lms-bridge'
							)
						)}
						onChange={(val) =>
							update(
								'checkout_page_id',
								parseInt(val, 10) || 0
							)
						}
					/>

					<SelectControl
						label={__(
							'Membership Levels Page',
							'simple-lms-bridge'
						)}
						value={settings.levels_page_id}
						options={pageOptions(
							__('— None —', 'simple-lms-bridge')
						)}
						onChange={(val) =>
							update(
								'levels_page_id',
								parseInt(val, 10) || 0
							)
						}
					/>
				</PanelBody>

				<PanelBody
					title={__(
						'Certificate Fields',
						'simple-lms-bridge'
					)}
					initialOpen={false}
				>
					<p className="slms-panel-desc">
						{__(
							'Gravity Forms field IDs used to populate and match certificate PDFs.',
							'simple-lms-bridge'
						)}
					</p>
					<TextControl
						label={__(
							'State Field ID',
							'simple-lms-bridge'
						)}
						type="number"
						value={settings.cert_state_field_id}
						onChange={(val) =>
							update(
								'cert_state_field_id',
								parseInt(val, 10) || 0
							)
						}
					/>
					<TextControl
						label={__(
							'Course URL Field ID',
							'simple-lms-bridge'
						)}
						type="number"
						value={settings.cert_course_field_id}
						onChange={(val) =>
							update(
								'cert_course_field_id',
								parseInt(val, 10) || 0
							)
						}
					/>
				</PanelBody>

				<PanelBody
					title={__('Tools', 'simple-lms-bridge')}
					initialOpen={false}
				>
					<p className="slms-panel-desc">
						{__(
							'Import legacy _lms_progress meta into the queryable progress table. Safe to run repeatedly.',
							'simple-lms-bridge'
						)}
					</p>
					<Button
						variant="secondary"
						isBusy={backfilling}
						disabled={backfilling}
						onClick={handleBackfill}
					>
						{backfilling
							? __('Backfilling…', 'simple-lms-bridge')
							: __(
									'Backfill Progress Table',
									'simple-lms-bridge'
							  )}
					</Button>
				</PanelBody>
			</Panel>

			<div className="slms-save-bar">
				<Button
					variant="primary"
					isBusy={saving}
					disabled={saving}
					onClick={handleSave}
				>
					{saving
						? __('Saving…', 'simple-lms-bridge')
						: __('Save Settings', 'simple-lms-bridge')}
				</Button>
			</div>
		</div>
	);
};

export default Settings;
