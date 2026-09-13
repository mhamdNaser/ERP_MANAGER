import { ChevronDown } from "lucide-react";
import { useEffect, useRef, useState } from "react";

export function AutocompleteInput({
  value,
  onChange,
  options = [],
  placeholder = "",
  disabled = false,
}) {
  const [open, setOpen] = useState(false);
  const [filtered, setFiltered] = useState(options);
  const inputRef = useRef(null);
  const dropdownRef = useRef(null);

  useEffect(() => {
    const trimmed = value.trim().toLowerCase();
    if (trimmed) {
      const matches = options.filter((opt) =>
        opt.toLowerCase().includes(trimmed)
      );
      setFiltered(matches);
      setOpen(matches.length > 0);
    } else {
      setFiltered(options);
      setOpen(false);
    }
  }, [value, options]);

  useEffect(() => {
    function handleClickOutside(event) {
      if (
        dropdownRef.current &&
        !dropdownRef.current.contains(event.target) &&
        !inputRef.current?.contains(event.target)
      ) {
        setOpen(false);
      }
    }

    document.addEventListener("mousedown", handleClickOutside);
    return () => document.removeEventListener("mousedown", handleClickOutside);
  }, []);

  return (
    <div className="relative">
      <div className="relative flex items-center">
        <input
          ref={inputRef}
          type="text"
          value={value}
          onChange={(e) => onChange(e.target.value)}
          onFocus={() => value.trim() && setOpen(true)}
          placeholder={placeholder}
          disabled={disabled}
          className="input pe-10"
        />
        <button
          type="button"
          className="absolute end-1 inline-grid h-7 w-7 place-items-center rounded text-muted hover:bg-canvas hover:text-ink disabled:cursor-not-allowed disabled:opacity-50"
          onClick={() => setOpen(!open)}
          disabled={disabled}
          aria-expanded={open}
        >
          <ChevronDown size={16} className={open ? "scale-y-[-1]" : ""} />
        </button>
      </div>

      {open && filtered.length > 0 && (
        <div
          ref={dropdownRef}
          className="absolute inset-x-0 top-[calc(100%+4px)] z-50 max-h-60 overflow-y-auto rounded border border-line bg-surface shadow-sm"
        >
          {filtered.map((option) => (
            <button
              key={option}
              type="button"
              className="block w-full px-3 py-2 text-start text-[13px] text-ink hover:bg-canvas"
              onClick={() => {
                onChange(option);
                setOpen(false);
              }}
            >
              {option}
            </button>
          ))}
        </div>
      )}
    </div>
  );
}
