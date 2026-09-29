import type { AppSettings } from '@journey/shared';
import { getDb } from '../db';

interface SettingsRow {
  id: number;
  default_facetime_minutes: number;
  default_monthly_frequency: number;
  default_working_days_csv: string;
  default_working_hours_start: string;
  default_working_hours_end: string;
}

function rowToSettings(row: SettingsRow): AppSettings {
  return {
    defaultFacetimeMinutes: row.default_facetime_minutes,
    defaultMonthlyFrequency: row.default_monthly_frequency,
    defaultWorkingDays: row.default_working_days_csv
      .split(',')
      .map((s) => Number(s.trim()))
      .filter((n) => Number.isFinite(n)),
    defaultWorkingHoursStart: row.default_working_hours_start,
    defaultWorkingHoursEnd: row.default_working_hours_end,
  };
}

export function getSettings(): AppSettings {
  const row = getDb().prepare('SELECT * FROM app_settings WHERE id = 1').get() as SettingsRow;
  return rowToSettings(row);
}

export function updateSettings(s: AppSettings): void {
  getDb()
    .prepare(
      `UPDATE app_settings SET
        default_facetime_minutes = ?,
        default_monthly_frequency = ?,
        default_working_days_csv = ?,
        default_working_hours_start = ?,
        default_working_hours_end = ?,
        updated_at = CURRENT_TIMESTAMP
       WHERE id = 1`,
    )
    .run(
      s.defaultFacetimeMinutes,
      s.defaultMonthlyFrequency,
      s.defaultWorkingDays.join(','),
      s.defaultWorkingHoursStart,
      s.defaultWorkingHoursEnd,
    );
}
