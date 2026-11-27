import { useEffect, useMemo, useState } from 'react';

async function apiRequest(path, options = {}) {
  const response = await fetch(`${window.simpleLMSAdmin.restUrl}${path}`, {
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': window.simpleLMSAdmin.nonce,
    },
    ...options,
  });
  const payload = await response.json().catch(() => ({}));
  if (!response.ok) throw new Error(payload.message || 'The curriculum could not be updated.');
  return payload;
}

export default function CurriculumBuilder() {
  const [courses, setCourses] = useState([]);
  const [lessons, setLessons] = useState([]);
  const [courseId, setCourseId] = useState('');
  const [lessonIds, setLessonIds] = useState([]);
  const [search, setSearch] = useState('');
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [message, setMessage] = useState('');

  useEffect(() => {
    const controller = new AbortController();
    Promise.all([
      apiRequest('/courses?per_page=200', { signal: controller.signal }),
      apiRequest('/lessons?per_page=200', { signal: controller.signal }),
    ])
      .then(([coursePayload, lessonPayload]) => {
        setCourses(coursePayload.courses || []);
        setLessons(lessonPayload.lessons || []);
        setCourseId(String(coursePayload.courses?.[0]?.id || ''));
      })
      .catch((error) => {
        if (error.name !== 'AbortError') setMessage(error.message);
      })
      .finally(() => setLoading(false));
    return () => controller.abort();
  }, []);

  const selectedCourse = courses.find((course) => String(course.id) === courseId);
  useEffect(() => {
    setLessonIds(selectedCourse?.lesson_ids || []);
    setMessage('');
  }, [selectedCourse?.id]);

  const lessonMap = useMemo(() => new Map(lessons.map((lesson) => [lesson.id, lesson])), [lessons]);
  const available = lessons.filter((lesson) => {
    const matchesSearch = lesson.title.toLowerCase().includes(search.trim().toLowerCase());
    return matchesSearch && !lessonIds.includes(lesson.id);
  });

  function move(index, direction) {
    const target = index + direction;
    if (target < 0 || target >= lessonIds.length) return;
    setLessonIds((current) => {
      const next = [...current];
      [next[index], next[target]] = [next[target], next[index]];
      return next;
    });
  }

  async function save(event) {
    event.preventDefault();
    if (!courseId) return;
    setSaving(true);
    setMessage('');
    try {
      const payload = await apiRequest(`/courses/${courseId}/lessons`, {
        method: 'POST',
        body: JSON.stringify({ lesson_ids: lessonIds }),
      });
      setLessonIds(payload.lesson_ids);
      setCourses((current) => current.map((course) => (
        course.id === Number(courseId) ? { ...course, lesson_ids: payload.lesson_ids } : course
      )));
      setMessage('Curriculum saved. Shared lessons remain a single canonical post.');
    } catch (error) {
      setMessage(error.message);
    } finally {
      setSaving(false);
    }
  }

  if (loading) return <p className="p-6 text-sm text-slate-500">Loading curriculum…</p>;

  return (
    <form onSubmit={save} className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(20rem,0.7fr)]">
      <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div className="flex flex-wrap items-end justify-between gap-4">
          <div>
            <h2 className="text-lg font-semibold text-[#011638]">Ordered curriculum</h2>
            <p className="mt-1 text-sm text-slate-500">One canonical lesson can appear in multiple courses.</p>
          </div>
          <label className="block min-w-64 flex-1 space-y-2">
            <span className="text-sm font-medium text-slate-700">Course</span>
            <select
              value={courseId}
              onChange={(event) => setCourseId(event.target.value)}
              className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"
            >
              {courses.map((course) => <option key={course.id} value={course.id}>{course.title}</option>)}
            </select>
          </label>
        </div>

        <ol className="mt-6 space-y-3">
          {lessonIds.map((id, index) => {
            const lesson = lessonMap.get(id);
            return (
              <li key={id} className="flex items-center gap-3 rounded-xl border border-slate-200 p-3">
                <span className="w-6 text-sm font-semibold text-slate-400">{index + 1}</span>
                <div className="min-w-0 flex-1">
                  <p className="truncate text-sm font-medium text-slate-900">{lesson?.title || `Lesson #${id}`}</p>
                  <p className="text-xs text-slate-500">
                    {lesson?.video_id ? `Presto video #${lesson.video_id}` : 'No Presto video ID'}
                  </p>
                </div>
                <div className="flex gap-1">
                  <button type="button" onClick={() => move(index, -1)} disabled={index === 0} aria-label={`Move ${lesson?.title || id} up`} className="rounded-lg border border-slate-200 px-2.5 py-1.5 text-sm disabled:opacity-40">↑</button>
                  <button type="button" onClick={() => move(index, 1)} disabled={index === lessonIds.length - 1} aria-label={`Move ${lesson?.title || id} down`} className="rounded-lg border border-slate-200 px-2.5 py-1.5 text-sm disabled:opacity-40">↓</button>
                  <button type="button" onClick={() => setLessonIds((current) => current.filter((lessonId) => lessonId !== id))} className="rounded-lg px-2.5 py-1.5 text-sm text-red-700 hover:bg-red-50">Remove</button>
                </div>
              </li>
            );
          })}
        </ol>
        {!lessonIds.length && <p className="mt-6 rounded-xl bg-slate-50 p-4 text-sm text-slate-500">No lessons assigned yet.</p>}
      </section>

      <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 className="text-lg font-semibold text-[#011638]">Add existing lessons</h2>
        <label className="mt-4 block space-y-2">
          <span className="text-sm font-medium text-slate-700">Search lessons</span>
          <input
            type="search"
            value={search}
            onChange={(event) => setSearch(event.target.value)}
            placeholder="Title or lesson content"
            className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
          />
        </label>
        <div className="mt-4 max-h-96 space-y-2 overflow-y-auto">
          {available.map((lesson) => (
            <button
              key={lesson.id}
              type="button"
              onClick={() => setLessonIds((current) => [...current, lesson.id])}
              className="flex w-full items-center justify-between gap-3 rounded-lg border border-slate-200 p-3 text-left hover:border-[#b7c3f3] hover:bg-slate-50"
            >
              <span>
                <span className="block text-sm font-medium text-slate-900">{lesson.title}</span>
                <span className="block text-xs text-slate-500">
                  {lesson.course_ids.length} assigned course{lesson.course_ids.length === 1 ? '' : 's'}
                </span>
              </span>
              <span className="text-lg text-[#011638]">+</span>
            </button>
          ))}
          {!available.length && <p className="text-sm text-slate-500">No matching lessons are available.</p>}
        </div>
        <div className="mt-6 flex items-center gap-4 border-t border-slate-100 pt-5">
          <button type="submit" disabled={saving || !courseId} className="rounded-lg bg-[#011638] px-5 py-2.5 text-sm font-semibold text-white disabled:opacity-60">
            {saving ? 'Saving…' : 'Save Curriculum'}
          </button>
          <span className="text-xs text-slate-500">{lessonIds.length} lesson{lessonIds.length === 1 ? '' : 's'}</span>
        </div>
        {message && <p role="status" className="mt-4 text-sm text-slate-700">{message}</p>}
      </section>
    </form>
  );
}
