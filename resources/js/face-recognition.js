const faceForms = document.querySelectorAll('[data-face-mode]');
const faceModal = document.querySelector('[data-face-modal]');
const cameraVideo = faceModal?.querySelector('[data-face-video]');
const modalStatus = faceModal?.querySelector('[data-face-modal-status]');
const captureButton = faceModal?.querySelector('[data-face-capture]');
let cameraStream = null;
let modelLoadPromise = null;

const setModalStatus = (message) => {
    if (modalStatus) {
        modalStatus.textContent = message;
    }
};

const setFormStatus = (form, message) => {
    const status = form.querySelector('[data-face-status]');

    if (status) {
        status.textContent = message;
    }
};

const stopCamera = () => {
    cameraStream?.getTracks().forEach((track) => track.stop());
    cameraStream = null;

    if (cameraVideo) {
        cameraVideo.srcObject = null;
    }
};

const closeCamera = () => {
    stopCamera();
    faceModal?.classList.add('hidden');
    faceModal?.classList.remove('flex');
    faceModal?.setAttribute('aria-hidden', 'true');
};

const loadFaceModels = (modelsUrl) => {
    if (!modelLoadPromise) {
        modelLoadPromise = import('@vladmandic/face-api').then(async (faceapi) => {
            await Promise.all([
                faceapi.nets.tinyFaceDetector.loadFromUri(modelsUrl),
                faceapi.nets.faceLandmark68Net.loadFromUri(modelsUrl),
                faceapi.nets.faceRecognitionNet.loadFromUri(modelsUrl),
            ]);
            await faceapi.tf.ready();

            return faceapi;
        });
    }

    return modelLoadPromise;
};

const openCamera = async (form) => {
    if (!navigator.mediaDevices?.getUserMedia) {
        setFormStatus(form, 'Este navegador no permite acceder a la cámara.');
        return;
    }

    faceModal?.classList.remove('hidden');
    faceModal?.classList.add('flex');
    faceModal?.setAttribute('aria-hidden', 'false');
    captureButton.disabled = true;
    setModalStatus('Cargando el detector facial…');

    try {
        await loadFaceModels(form.dataset.modelsUrl);
        cameraStream = await navigator.mediaDevices.getUserMedia({
            audio: false,
            video: {
                facingMode: 'user',
                width: { ideal: 640 },
                height: { ideal: 480 },
            },
        });
        cameraVideo.srcObject = cameraStream;
        await cameraVideo.play();
        captureButton.disabled = false;
        setModalStatus('Centra tu rostro en la cámara y pulsa “Capturar rostro”.');
    } catch (error) {
        closeCamera();
        const message = error.name === 'NotAllowedError'
            ? 'Debes permitir el acceso a la cámara para continuar.'
            : 'No pudimos iniciar la cámara. Comprueba que esté disponible e inténtalo nuevamente.';
        setFormStatus(form, message);
    }
};

const captureDescriptor = async () => {
    const faceapi = await modelLoadPromise;
    const descriptors = [];

    for (let sampleIndex = 0; sampleIndex < 3; sampleIndex++) {
        setModalStatus(sampleIndex === 0 ? 'Detectando rostro…' : `Rostro detectado. Captura ${sampleIndex + 1} de 3…`);
        const detections = await faceapi
            .detectAllFaces(cameraVideo, new faceapi.TinyFaceDetectorOptions({ inputSize: 320, scoreThreshold: 0.55 }))
            .withFaceLandmarks()
            .withFaceDescriptors();

        if (detections.length !== 1) {
            throw new Error(detections.length === 0
                ? 'No se detectó un rostro. Ajusta la iluminación y vuelve a intentarlo.'
                : 'Debe aparecer una sola persona frente a la cámara.');
        }

        descriptors.push(Array.from(detections[0].descriptor));
        await new Promise((resolve) => window.setTimeout(resolve, 180));
    }

    return descriptors[0].map((_, valueIndex) => (
        descriptors.reduce((total, sample) => total + sample[valueIndex], 0) / descriptors.length
    ));
};

const addDescriptorFields = (form, descriptor) => {
    form.querySelectorAll('[data-generated-face-descriptor]').forEach((field) => field.remove());

    descriptor.forEach((value, valueIndex) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = `face_descriptor[${valueIndex}]`;
        input.value = String(value);
        input.dataset.generatedFaceDescriptor = 'true';
        form.append(input);
    });
};

const submitCapturedDescriptor = (form, descriptor) => {
    if (form.dataset.faceMode === 'profile' && !form.reportValidity()) {
        return;
    }

    addDescriptorFields(form, descriptor);
    setFormStatus(form, form.dataset.faceMode === 'login'
        ? 'Rostro detectado. Verificando identidad…'
        : 'Rostro detectado. Guardando el descriptor cifrado…');

    if (form.dataset.faceMode === 'login') {
        form.action = form.dataset.faceLoginUrl;
    }

    HTMLFormElement.prototype.submit.call(form);
};

faceForms.forEach((form) => {
    form.querySelector('[data-face-open]')?.addEventListener('click', async () => {
        if (!form.reportValidity()) {
            return;
        }

        setFormStatus(form, '');
        await openCamera(form);
    });
});

captureButton?.addEventListener('click', async () => {
    const form = [...faceForms].find((candidate) => candidate.dataset.faceMode);

    if (!form) {
        return;
    }

    captureButton.disabled = true;

    try {
        const descriptor = await captureDescriptor();
        closeCamera();
        submitCapturedDescriptor(form, descriptor);
    } catch (error) {
        captureButton.disabled = false;
        setModalStatus(error.message || 'No pudimos reconocer el rostro. Inténtalo nuevamente.');
    }
});

faceModal?.querySelectorAll('[data-face-close]').forEach((button) => {
    button.addEventListener('click', closeCamera);
});

faceModal?.addEventListener('click', (event) => {
    if (event.target === faceModal) {
        closeCamera();
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && faceModal && !faceModal.classList.contains('hidden')) {
        closeCamera();
    }
});