export type VideoStatus = 'queued' | 'processing' | 'done' | 'failed';

export type VideoStatusPayload = {
    id: string;
    status: VideoStatus;
    video_url: string | null;
    error_code: string | null;
    error_message: string | null;
    can_retry: boolean;
    queue_position: number | null;
};

export type Video = VideoStatusPayload & {
    mode: 'text' | 'image';
    prompt: string;
    aspect_ratio: '9:16' | '16:9';
    resolution: '360p' | '720p' | '1080p';
    created_at: string | null;
};
