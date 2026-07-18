const PRINT_FRAME_ATTRIBUTE = "data-direct-print-frame";

export const directPrint = (url: string): void => {
    document.querySelectorAll(`[${PRINT_FRAME_ATTRIBUTE}]`).forEach((frame) =>
        frame.remove(),
    );

    const frame = document.createElement("iframe");
    frame.setAttribute(PRINT_FRAME_ATTRIBUTE, "true");
    frame.setAttribute("aria-hidden", "true");
    frame.title = "Dokumen cetak";
    Object.assign(frame.style, {
        position: "fixed",
        right: "0",
        bottom: "0",
        width: "1px",
        height: "1px",
        border: "0",
        opacity: "0",
        pointerEvents: "none",
    });

    let cleanupTimer: number | undefined;
    const cleanup = () => {
        if (cleanupTimer !== undefined) window.clearTimeout(cleanupTimer);
        frame.remove();
    };

    frame.addEventListener(
        "load",
        () => {
            frame.contentWindow?.addEventListener("afterprint", cleanup, {
                once: true,
            });
            cleanupTimer = window.setTimeout(cleanup, 60_000);
        },
        { once: true },
    );

    frame.src = url;
    document.body.appendChild(frame);
};
