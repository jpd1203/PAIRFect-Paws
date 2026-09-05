const CAMERA_CONSTRAINTS = {
    audio: false,
    video: {
        facingMode: { ideal: 'environment' },
        width: { ideal: 1280 },
        height: { ideal: 720 },
        frameRate: { ideal: 30, max: 30 },
    },
};

const DEFAULT_RECORDING_DURATION_SECONDS = 3;
const VIDEO_BITS_PER_SECOND = 2_500_000;
const VIDEO_MIME_TYPES = [
    'video/webm;codecs=vp9',
    'video/webm;codecs=vp8',
    'video/webm',
    'video/mp4;codecs=avc1.42E01E',
    'video/mp4',
];

function cameraErrorMessage(error) {
    if (!window.isSecureContext) {
        return 'Camera access is blocked because this page is not using HTTPS. Open the secure site and try again.';
    }

    switch (error?.name) {
        case 'NotAllowedError':
        case 'SecurityError':
            return 'Camera permission was denied. Allow camera access in your browser settings, then try again.';
        case 'NotFoundError':
        case 'DevicesNotFoundError':
            return 'No camera was found on this device.';
        case 'NotReadableError':
        case 'TrackStartError':
            return 'The camera is already in use or could not be started. Close other camera apps and try again.';
        case 'OverconstrainedError':
        case 'ConstraintNotSatisfiedError':
            return 'This camera does not support the requested recording settings.';
        case 'NotSupportedError':
            return 'This browser cannot record a supported video format. Try a current version of Chrome, Edge, Firefox, or Safari.';
        default:
            return 'The camera could not be opened. Check browser permissions and try again.';
    }
}

function supportedVideoMimeType() {
    if (!window.MediaRecorder) return null;
    if (typeof window.MediaRecorder.isTypeSupported !== 'function') return '';

    return VIDEO_MIME_TYPES.find((type) => window.MediaRecorder.isTypeSupported(type)) ?? null;
}

function recordedFilename(blob) {
    const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
    const extension = blob?.type?.toLowerCase().includes('mp4') ? 'mp4' : 'webm';

    return `live-checkin-${timestamp}.${extension}`;
}

function initCameraForm(form) {
    const camera = form.querySelector('[data-camera-capture]');
    const liveVideo = camera?.querySelector('[data-camera-video]');
    const previewVideo = camera?.querySelector('[data-camera-preview]');
    const placeholder = camera?.querySelector('[data-camera-placeholder]');
    const status = camera?.querySelector('[data-camera-status]');
    const challengeStatus = camera?.querySelector('[data-camera-challenge-status]');
    const cameraError = camera?.querySelector('[data-camera-error]');
    const startButton = camera?.querySelector('[data-camera-start]');
    const recordButton = camera?.querySelector('[data-camera-record]');
    const retakeButton = camera?.querySelector('[data-camera-retake]');
    const submitButton = form.querySelector('[data-camera-submit]');
    const submitHint = form.querySelector('[data-camera-submit-hint]');
    const capturedAtInput = form.querySelector('[data-camera-captured-at]');
    const recordingDurationInput = form.querySelector('[data-camera-recording-duration]');
    const challengeUrl = form.dataset.cameraChallengeUrl;

    if (!camera || !liveVideo || !previewVideo || !placeholder || !status
        || !startButton || !recordButton || !retakeButton || !submitButton) {
        return;
    }

    let stream = null;
    let mediaRecorder = null;
    let capturedVideo = null;
    let previewUrl = null;
    let submitting = false;
    let recordingTimer = null;
    let recordingCountdownTimer = null;
    let captureGeneration = 0;
    let challengeId = null;
    let challengeToken = null;
    let challengeDeadline = 0;
    let challengeTimer = null;
    let challengeRequest = null;
    let requiredDurationSeconds = DEFAULT_RECORDING_DURATION_SECONDS;

    const challengeIsActive = () => Boolean(
        challengeId
        && challengeToken
        && challengeDeadline > performance.now(),
    );

    const setStatus = (message, state = 'idle') => {
        status.textContent = message;
        camera.dataset.cameraState = state;
        camera.setAttribute(
            'aria-busy',
            ['loading', 'securing', 'recording', 'submitting'].includes(state) ? 'true' : 'false',
        );
    };

    const setCameraError = (message = '') => {
        if (cameraError) cameraError.textContent = message;
    };

    const updateSubmitState = () => {
        submitButton.disabled = submitting || !capturedVideo || !challengeIsActive();

        if (submitHint) {
            submitHint.textContent = capturedVideo && challengeIsActive()
                ? 'Your live 3-second video is secured and ready to submit.'
                : 'Record a live 3-second video to enable submission.';
        }
    };

    const stopStream = () => {
        stream?.getTracks().forEach((track) => track.stop());
        stream = null;
        liveVideo.srcObject = null;
    };

    const clearRecordingTimers = () => {
        if (recordingTimer) window.clearTimeout(recordingTimer);
        if (recordingCountdownTimer) window.clearInterval(recordingCountdownTimer);
        recordingTimer = null;
        recordingCountdownTimer = null;
    };

    const stopActiveRecording = () => {
        clearRecordingTimers();

        if (mediaRecorder && mediaRecorder.state !== 'inactive') {
            try {
                mediaRecorder.stop();
            } catch {
                // The recorder may already be stopping after a track ended.
            }
        }

        mediaRecorder = null;
    };

    const revokePreviewUrl = () => {
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        previewUrl = null;
    };

    const clearChallenge = () => {
        challengeRequest?.abort();
        challengeRequest = null;
        if (challengeTimer) window.clearInterval(challengeTimer);
        challengeTimer = null;
        challengeId = null;
        challengeToken = null;
        challengeDeadline = 0;
        requiredDurationSeconds = DEFAULT_RECORDING_DURATION_SECONDS;

        if (challengeStatus) {
            challengeStatus.textContent = '';
            challengeStatus.hidden = true;
        }
    };

    const clearCapture = () => {
        captureGeneration += 1;
        stopActiveRecording();
        capturedVideo = null;
        clearChallenge();
        if (capturedAtInput) capturedAtInput.value = '';
        if (recordingDurationInput) recordingDurationInput.value = '';
        revokePreviewUrl();
        previewVideo.pause();
        previewVideo.removeAttribute('src');
        previewVideo.load();
        previewVideo.hidden = true;
        placeholder.hidden = false;
        retakeButton.hidden = true;
        startButton.hidden = false;
        recordButton.hidden = true;
        recordButton.disabled = true;
        updateSubmitState();
    };

    const expireCaptureSession = () => {
        stopStream();
        clearCapture();
        liveVideo.hidden = true;
        setCameraError('The secure camera session expired. Start the camera and record a new video.');
        setStatus('Secure camera session expired.', 'error');
    };

    const startChallengeCountdown = (expiresInSeconds) => {
        challengeDeadline = performance.now() + (expiresInSeconds * 1000);

        const updateCountdown = () => {
            const secondsRemaining = Math.max(
                0,
                Math.ceil((challengeDeadline - performance.now()) / 1000),
            );

            if (challengeStatus) {
                challengeStatus.hidden = false;
                challengeStatus.textContent = `Secure recording session: ${secondsRemaining} seconds remaining.`;
            }

            updateSubmitState();
            if (secondsRemaining <= 0) expireCaptureSession();
        };

        updateCountdown();
        challengeTimer = window.setInterval(updateCountdown, 1000);
    };

    const requestCaptureChallenge = async () => {
        if (!challengeUrl) throw new Error('The secure camera session endpoint is unavailable.');

        clearChallenge();
        const controller = new AbortController();
        challengeRequest = controller;
        const csrfToken = form.querySelector('input[name="_token"]')?.value
            ?? document.querySelector('meta[name="csrf-token"]')?.content
            ?? '';

        try {
            const response = await fetch(challengeUrl, {
                method: 'POST',
                credentials: 'same-origin',
                signal: controller.signal,
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const payload = await response.json().catch(() => null);

            if (!response.ok) {
                if (response.status === 429) {
                    throw new Error('Too many camera attempts. Wait a minute and try again.');
                }
                if ([401, 403, 419].includes(response.status)) {
                    throw new Error('Your session expired or cannot access this check-in. Sign in again.');
                }
                throw new Error(payload?.message || 'The secure camera session could not be started. Please try again.');
            }

            const expiresInSeconds = Number(payload?.expires_in_seconds);
            const responseDuration = Number(payload?.required_duration_seconds);
            if (!payload?.challenge_id || !payload?.challenge_token
                || !Number.isFinite(expiresInSeconds) || expiresInSeconds <= 0) {
                throw new Error('The server returned an invalid secure camera session. Please try again.');
            }

            requiredDurationSeconds = Number.isFinite(responseDuration) && responseDuration > 0
                ? responseDuration
                : DEFAULT_RECORDING_DURATION_SECONDS;
            challengeId = payload.challenge_id;
            challengeToken = payload.challenge_token;
            startChallengeCountdown(expiresInSeconds);
        } finally {
            if (challengeRequest === controller) challengeRequest = null;
        }
    };

    const clearValidationErrors = () => {
        form.querySelectorAll('[data-validation-for]').forEach((element) => {
            element.textContent = '';
        });
    };

    const renderValidationErrors = (errors = {}) => {
        let firstInvalidField = null;
        const challengeFields = [
            'capture_challenge',
            'capture_challenge_id',
            'capture_challenge_token',
        ];

        Object.entries(errors).forEach(([field, messages]) => {
            const displayField = challengeFields.includes(field) ? 'capture_challenge' : field;
            const target = [...form.querySelectorAll('[data-validation-for]')]
                .find((element) => element.dataset.validationFor === displayField);
            const message = Array.isArray(messages) ? messages[0] : messages;

            if (target) {
                target.textContent = message ?? 'This field is invalid.';
                firstInvalidField ??= target;
            }
        });

        firstInvalidField?.setAttribute('tabindex', '-1');
        firstInvalidField?.focus({ preventScroll: true });
        firstInvalidField?.scrollIntoView({ behavior: 'smooth', block: 'center' });

        return Object.keys(errors).some((field) => [
            'video',
            'camera_captured_at',
            'recording_duration_ms',
            ...challengeFields,
        ].includes(field));
    };

    const startCamera = async () => {
        setCameraError('');
        clearCapture();
        stopStream();
        liveVideo.hidden = true;
        startButton.disabled = true;
        updateSubmitState();
        setStatus('Requesting camera access...', 'loading');

        if (!navigator.mediaDevices?.getUserMedia || !window.MediaRecorder) {
            const message = window.isSecureContext
                ? 'This browser does not support live video recording. Try a current version of Chrome, Edge, Firefox, or Safari.'
                : cameraErrorMessage();
            setCameraError(message);
            setStatus('Camera recording unavailable.', 'error');
            startButton.disabled = false;
            return;
        }

        try {
            stream = await navigator.mediaDevices.getUserMedia(CAMERA_CONSTRAINTS);
            liveVideo.srcObject = stream;
            await liveVideo.play();

            setStatus('Establishing a secure, single-use recording session...', 'securing');
            await requestCaptureChallenge();

            placeholder.hidden = true;
            liveVideo.hidden = false;
            startButton.hidden = true;
            recordButton.hidden = false;
            recordButton.disabled = false;
            recordButton.textContent = `Record ${requiredDurationSeconds}-Second Video`;
            setStatus(`Camera secured. Keep your pet in view, then record the ${requiredDurationSeconds}-second video.`, 'ready');
        } catch (error) {
            stopStream();
            clearChallenge();
            liveVideo.hidden = true;
            startButton.hidden = false;
            recordButton.hidden = true;
            setCameraError(error?.name === 'AbortError' ? '' : (error?.message || cameraErrorMessage(error)));
            setStatus(error?.name === 'AbortError' ? 'Camera session restarted.' : 'Camera could not be started.', 'error');
        } finally {
            startButton.disabled = false;
        }
    };

    const recordVideo = async () => {
        setCameraError('');

        if (!stream || liveVideo.videoWidth === 0 || liveVideo.videoHeight === 0) {
            setCameraError('The camera is still preparing. Wait a moment and try again.');
            return;
        }

        if (!challengeIsActive()) {
            expireCaptureSession();
            return;
        }

        const mimeType = supportedVideoMimeType();
        if (mimeType === null) {
            setCameraError('This browser does not support live video recording.');
            return;
        }

        recordButton.disabled = true;
        startButton.hidden = true;
        retakeButton.hidden = true;
        const generation = captureGeneration;
        const chunks = [];
        let recordingStartedAt = 0;
        let recordingStoppedAt = 0;

        try {
            const options = { videoBitsPerSecond: VIDEO_BITS_PER_SECOND };
            if (mimeType) options.mimeType = mimeType;
            const recorder = new window.MediaRecorder(stream, options);
            mediaRecorder = recorder;

            await new Promise((resolve, reject) => {
                const videoTrack = stream.getVideoTracks()[0];
                const removeTrackListener = () => videoTrack?.removeEventListener('ended', handleTrackEnded);
                const handleTrackEnded = () => {
                    removeTrackListener();
                    reject(new Error('The camera stopped before the recording was complete. Please record again.'));

                    if (recorder.state === 'recording') recorder.stop();
                };

                videoTrack?.addEventListener('ended', handleTrackEnded, { once: true });
                recorder.addEventListener('dataavailable', (event) => {
                    if (event.data?.size > 0) chunks.push(event.data);
                });
                recorder.addEventListener('error', (event) => {
                    removeTrackListener();
                    reject(event.error || new Error('The video recorder stopped unexpectedly.'));
                }, { once: true });
                recorder.addEventListener('stop', () => {
                    removeTrackListener();
                    if (!recordingStoppedAt) recordingStoppedAt = performance.now();
                    resolve();
                }, { once: true });
                recorder.addEventListener('start', () => {
                    recordingStartedAt = performance.now();
                    const durationMilliseconds = requiredDurationSeconds * 1000;

                    const updateRecordingCountdown = () => {
                        const elapsed = performance.now() - recordingStartedAt;
                        const secondsRemaining = Math.max(0, Math.ceil((durationMilliseconds - elapsed) / 1000));
                        setStatus(`Recording now - ${secondsRemaining} second${secondsRemaining === 1 ? '' : 's'} remaining. Keep your pet in view.`, 'recording');
                    };

                    updateRecordingCountdown();
                    recordingCountdownTimer = window.setInterval(updateRecordingCountdown, 250);
                    recordingTimer = window.setTimeout(() => {
                        if (recorder.state === 'recording') {
                            recordingStoppedAt = performance.now();
                            recorder.stop();
                        }
                    }, durationMilliseconds);
                }, { once: true });

                recorder.start(250);
            });

            clearRecordingTimers();
            mediaRecorder = null;
            if (generation !== captureGeneration) return;
            if (!challengeIsActive()) {
                expireCaptureSession();
                return;
            }

            const recordedType = recorder.mimeType || mimeType || chunks[0]?.type || 'video/webm';
            capturedVideo = new Blob(chunks, { type: recordedType });
            if (!capturedVideo.size) throw new Error('The camera produced an empty video. Please record again.');

            const durationMilliseconds = Math.max(0, Math.round(recordingStoppedAt - recordingStartedAt));
            if (capturedAtInput) capturedAtInput.value = new Date().toISOString();
            if (recordingDurationInput) recordingDurationInput.value = String(durationMilliseconds);
            revokePreviewUrl();
            previewUrl = URL.createObjectURL(capturedVideo);
            previewVideo.src = previewUrl;
            previewVideo.hidden = false;
            previewVideo.load();
            liveVideo.hidden = true;
            placeholder.hidden = true;
            recordButton.hidden = true;
            retakeButton.hidden = false;
            stopStream();
            setStatus(`${requiredDurationSeconds}-second video recorded and linked to this secure session. Review it before submitting.`, 'captured');
            updateSubmitState();
        } catch (error) {
            if (generation !== captureGeneration) return;

            stopActiveRecording();
            capturedVideo = null;
            if (capturedAtInput) capturedAtInput.value = '';
            if (recordingDurationInput) recordingDurationInput.value = '';
            setCameraError(error?.message || 'The video could not be recorded. Please try again.');
            setStatus('Recording failed.', 'error');
            recordButton.disabled = false;
            updateSubmitState();
        }
    };

    const retakeVideo = async () => {
        clearCapture();
        await startCamera();
    };

    startButton.addEventListener('click', startCamera);
    recordButton.addEventListener('click', recordVideo);
    retakeButton.addEventListener('click', retakeVideo);

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (submitting) return;

        clearValidationErrors();

        if (!capturedVideo) {
            setCameraError('Record a live 3-second video before submitting this report.');
            camera.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }

        if (!challengeIsActive()) {
            expireCaptureSession();
            return;
        }

        submitting = true;
        updateSubmitState();
        setStatus('Submitting the secured welfare report...', 'submitting');
        const originalSubmitLabel = submitButton.textContent;
        submitButton.textContent = 'Submitting...';

        try {
            const formData = new FormData(form);
            formData.delete('photo');
            formData.delete('video');
            formData.append('video', capturedVideo, recordedFilename(capturedVideo));
            formData.set('capture_challenge_id', challengeId);
            formData.set('capture_challenge_token', challengeToken);

            const csrfToken = form.querySelector('input[name="_token"]')?.value
                ?? document.querySelector('meta[name="csrf-token"]')?.content
                ?? '';

            const response = await fetch(form.action, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            const contentType = response.headers.get('content-type') ?? '';
            const payload = contentType.includes('application/json')
                ? await response.json()
                : null;

            if (!response.ok) {
                const errors = payload?.errors ?? {};
                const captureRejected = renderValidationErrors(errors);
                const firstError = Object.values(errors).flat()[0];

                if (captureRejected) {
                    stopStream();
                    clearCapture();
                    setStatus('The secured video was not accepted. Start the camera and try again.', 'error');
                } else {
                    setCameraError(firstError || payload?.message || 'The report could not be submitted. Please try again.');
                    setStatus('Submission was not completed.', 'error');
                }

                return;
            }

            stopStream();
            revokePreviewUrl();
            clearChallenge();

            if (response.redirected) {
                window.location.assign(response.url);
                return;
            }

            window.location.assign(payload?.redirect || form.dataset.successUrl || '/monitoring/my-checkins');
        } catch (error) {
            setCameraError(error?.message || 'A network error interrupted submission. Please try again.');
            setStatus('Submission was not completed.', 'error');
        } finally {
            submitting = false;
            submitButton.textContent = originalSubmitLabel;
            updateSubmitState();
        }
    });

    const cleanup = () => {
        captureGeneration += 1;
        stopActiveRecording();
        stopStream();
        revokePreviewUrl();
        clearChallenge();
    };

    window.addEventListener('pagehide', cleanup);
    window.addEventListener('beforeunload', cleanup);
    window.addEventListener('pageshow', (event) => {
        if (event.persisted) {
            clearCapture();
            setStatus('Camera access has not started.', 'idle');
        }
    });
    updateSubmitState();
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-post-adoption-camera-form]').forEach(initCameraForm);
});
