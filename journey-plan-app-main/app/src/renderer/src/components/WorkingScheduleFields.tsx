interface Props {
  workingDays: number[];
  workingHoursStart: string;
  workingHoursEnd: string;
  onChange: (next: {
    workingDays: number[];
    workingHoursStart: string;
    workingHoursEnd: string;
  }) => void;
}

const DAYS = [
  { value: 0, label: 'Sun' },
  { value: 1, label: 'Mon' },
  { value: 2, label: 'Tue' },
  { value: 3, label: 'Wed' },
  { value: 4, label: 'Thu' },
  { value: 5, label: 'Fri' },
  { value: 6, label: 'Sat' },
];

export function WorkingScheduleFields({
  workingDays,
  workingHoursStart,
  workingHoursEnd,
  onChange,
}: Props) {
  const toggleDay = (day: number) => {
    const set = new Set(workingDays);
    if (set.has(day)) set.delete(day);
    else set.add(day);
    onChange({
      workingDays: Array.from(set).sort(),
      workingHoursStart,
      workingHoursEnd,
    });
  };

  return (
    <>
      <fieldset className="day-picker">
        <legend>Working days</legend>
        {DAYS.map((d) => (
          <label key={d.value} className="day-toggle">
            <input
              type="checkbox"
              checked={workingDays.includes(d.value)}
              onChange={() => toggleDay(d.value)}
            />
            {d.label}
          </label>
        ))}
      </fieldset>
      <div className="row">
        <label>
          Start
          <input
            type="time"
            value={workingHoursStart}
            onChange={(e) =>
              onChange({ workingDays, workingHoursStart: e.target.value, workingHoursEnd })
            }
          />
        </label>
        <label>
          End
          <input
            type="time"
            value={workingHoursEnd}
            onChange={(e) =>
              onChange({ workingDays, workingHoursStart, workingHoursEnd: e.target.value })
            }
          />
        </label>
      </div>
    </>
  );
}
