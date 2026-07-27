import { useState, useCallback, useEffect } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { ChevronLeft, ChevronRight, X, Expand } from 'lucide-react';
import { ImageWithFallback } from '../../../components/ImageWithFallback';

type GalleryImage = {
  url: string;
  smallUrl?: string;
  largeUrl?: string;
  alt: string | null;
};

type GalleryLayout = 'thumbnails-left' | 'thumbnails-bottom' | 'dots';

function getGalleryLayout(): GalleryLayout {
  if (typeof window === 'undefined') return 'thumbnails-left';
  if (window.innerWidth < 768) return 'dots';
  const theme = document.documentElement.dataset.storefrontTheme;
  switch (theme) {
    case 'nordic-editorial':
      return 'thumbnails-bottom';
    case 'ocean-minimal':
      return 'dots';
    default:
      return 'thumbnails-left';
  }
}

function Lightbox({
  images,
  activeIndex,
  productName,
  onClose,
  onPrev,
  onNext,
}: {
  images: GalleryImage[];
  activeIndex: number;
  productName: string;
  onClose: () => void;
  onPrev: () => void;
  onNext: () => void;
}) {
  useEffect(() => {
    const handler = (event: KeyboardEvent) => {
      if (event.key === 'Escape') onClose();
      if (event.key === 'ArrowLeft') onPrev();
      if (event.key === 'ArrowRight') onNext();
    };
    document.addEventListener('keydown', handler);
    document.body.style.overflow = 'hidden';
    return () => {
      document.removeEventListener('keydown', handler);
      document.body.style.overflow = '';
    };
  }, [onClose, onPrev, onNext]);

  return (
    <motion.div
      className="fixed inset-0 z-[100] flex items-center justify-center bg-black/90 backdrop-blur-sm"
      initial={{ opacity: 0 }}
      animate={{ opacity: 1 }}
      exit={{ opacity: 0 }}
      transition={{ duration: 0.2 }}
      onClick={onClose}
      role="dialog"
      aria-modal="true"
      aria-label={`Image ${activeIndex + 1} sur ${images.length}`}
    >
      <button
        type="button"
        className="absolute right-4 top-4 z-10 flex h-10 w-10 cursor-pointer items-center justify-center rounded-full bg-white/10 text-white backdrop-blur transition-colors hover:bg-white/20"
        onClick={(event) => { event.stopPropagation(); onClose(); }}
        aria-label="Fermer"
      >
        <X className="h-5 w-5" />
      </button>

      <span className="absolute bottom-6 left-1/2 -translate-x-1/2 rounded-full bg-white/10 px-4 py-1.5 text-sm font-medium text-white backdrop-blur">
        {activeIndex + 1} / {images.length}
      </span>

      {images.length > 1 && (
        <button
          type="button"
          className="absolute left-4 top-1/2 z-10 flex h-12 w-12 -translate-y-1/2 cursor-pointer items-center justify-center rounded-full bg-white/10 text-white backdrop-blur transition-colors hover:bg-white/20"
          onClick={(event) => { event.stopPropagation(); onPrev(); }}
          aria-label="Image précédente"
        >
          <ChevronLeft className="h-6 w-6" />
        </button>
      )}

      <div className="flex max-h-[90vh] max-w-[90vw] items-center justify-center" onClick={(event) => event.stopPropagation()}>
        <AnimatePresence mode="wait">
          <motion.div
            key={activeIndex}
            initial={{ opacity: 0, scale: 0.92 }}
            animate={{ opacity: 1, scale: 1 }}
            exit={{ opacity: 0, scale: 0.92 }}
            transition={{ duration: 0.2 }}
          >
            <ImageWithFallback
              src={images[activeIndex].largeUrl ?? images[activeIndex].url}
              alt={images[activeIndex].alt ?? productName}
              className="max-h-[85vh] max-w-[85vw] rounded-lg object-contain"
            />
          </motion.div>
        </AnimatePresence>
      </div>

      {images.length > 1 && (
        <button
          type="button"
          className="absolute right-4 top-1/2 z-10 flex h-12 w-12 -translate-y-1/2 cursor-pointer items-center justify-center rounded-full bg-white/10 text-white backdrop-blur transition-colors hover:bg-white/20"
          onClick={(event) => { event.stopPropagation(); onNext(); }}
          aria-label="Image suivante"
        >
          <ChevronRight className="h-6 w-6" />
        </button>
      )}
    </motion.div>
  );
}

function ThumbnailsColumn({
  images,
  activeIndex,
  productName,
  onSelect,
}: {
  images: GalleryImage[];
  activeIndex: number;
  productName: string;
  onSelect: (index: number) => void;
}) {
  return (
    <div className="flex flex-col gap-2 overflow-y-auto p-2" role="tablist" aria-label="Sélection d'images">
      {images.map((image, index) => (
        <button
          key={image.url + index}
          type="button"
          role="tab"
          aria-selected={index === activeIndex}
          aria-label={`Image ${index + 1}`}
          className={`product-gallery__thumb ${index === activeIndex ? 'product-gallery__thumb--active' : ''}`}
          onClick={() => onSelect(index)}
        >
          <ImageWithFallback
            src={image.smallUrl ?? image.url}
            alt={image.alt ?? `${productName} - Image ${index + 1}`}
            className="h-full w-full object-cover"
          />
        </button>
      ))}
    </div>
  );
}

function ThumbnailsRow({
  images,
  activeIndex,
  productName,
  onSelect,
}: {
  images: GalleryImage[];
  activeIndex: number;
  productName: string;
  onSelect: (index: number) => void;
}) {
  return (
    <div className="flex gap-2 overflow-x-auto p-2" role="tablist" aria-label="Sélection d'images">
      {images.map((image, index) => (
        <button
          key={image.url + index}
          type="button"
          role="tab"
          aria-selected={index === activeIndex}
          aria-label={`Image ${index + 1}`}
          className={`product-gallery__thumb product-gallery__thumb--row ${index === activeIndex ? 'product-gallery__thumb--active' : ''}`}
          onClick={() => onSelect(index)}
        >
          <ImageWithFallback
            src={image.smallUrl ?? image.url}
            alt={image.alt ?? `${productName} - Image ${index + 1}`}
            className="h-full w-full object-cover"
          />
        </button>
      ))}
    </div>
  );
}

function DotsNav({
  count,
  activeIndex,
  onSelect,
}: {
  count: number;
  activeIndex: number;
  onSelect: (index: number) => void;
}) {
  return (
    <div className="absolute bottom-3 left-1/2 z-10 flex -translate-x-1/2 gap-2" role="tablist" aria-label="Sélection d'images">
      {Array.from({ length }, (_, index) => (
        <button
          key={index}
          type="button"
          role="tab"
          aria-selected={index === activeIndex}
          aria-label={`Image ${index + 1}`}
          className={`h-2 rounded-full transition-all duration-300 ${
            index === activeIndex
              ? 'w-6 bg-white shadow-sm'
              : 'w-2 bg-white/60 hover:bg-white/80'
          }`}
          onClick={() => onSelect(index)}
        />
      ))}
    </div>
  );
}

export function ProductImageGallery({
  images,
  productName,
}: {
  images: GalleryImage[];
  productName: string;
}) {
  const [activeIndex, setActiveIndex] = useState(0);
  const [lightboxOpen, setLightboxOpen] = useState(false);
  const [layout, setLayout] = useState<GalleryLayout>('thumbnails-left');

  useEffect(() => {
    setLayout(getGalleryLayout());
    const handler = () => setLayout(getGalleryLayout());
    window.addEventListener('resize', handler);
    return () => window.removeEventListener('resize', handler);
  }, []);

  useEffect(() => {
    setActiveIndex(0);
  }, [images]);

  const handlePrev = useCallback(() => {
    setActiveIndex((current) => (current > 0 ? current - 1 : images.length - 1));
  }, [images.length]);

  const handleNext = useCallback(() => {
    setActiveIndex((current) => (current < images.length - 1 ? current + 1 : 0));
  }, [images.length]);

  if (images.length === 0) {
    return (
      <div className="flex h-full min-h-[350px] items-center justify-center bg-[color:var(--ds-surface-container)]">
        <div className="flex flex-col items-center gap-2 text-[color:var(--ds-on-surface-variant)]">
          <Expand className="h-10 w-10 opacity-40" />
          <span className="text-sm font-medium">Aucune image</span>
        </div>
      </div>
    );
  }

  return (
    <>
      <div className={`product-gallery product-gallery--${layout}`}>
        {layout === 'thumbnails-left' && (
          <ThumbnailsColumn
            images={images}
            activeIndex={activeIndex}
            productName={productName}
            onSelect={setActiveIndex}
          />
        )}

        <div className="product-gallery__main">
          <button
            type="button"
            className="group relative flex h-full w-full cursor-pointer items-center justify-center overflow-hidden bg-[color:var(--ds-surface-container)]"
            onClick={() => setLightboxOpen(true)}
            aria-label="Agrandir l'image"
          >
            <AnimatePresence mode="wait">
              <motion.div
                key={activeIndex}
                initial={{ opacity: 0 }}
                animate={{ opacity: 1 }}
                exit={{ opacity: 0 }}
                transition={{ duration: 0.2 }}
                className="h-full w-full"
              >
                <ImageWithFallback
                  src={images[activeIndex].largeUrl ?? images[activeIndex].url}
                  alt={images[activeIndex].alt ?? productName}
                  className="h-full w-full object-cover"
                />
              </motion.div>
            </AnimatePresence>

            <div className="absolute inset-0 bg-black/0 transition-colors duration-200 group-hover:bg-black/5" />

            <div className="absolute right-3 top-3 flex h-8 w-8 items-center justify-center rounded-full bg-white/80 text-[color:var(--ds-on-surface)] opacity-0 shadow-sm backdrop-blur transition-all duration-200 group-hover:opacity-100">
              <Expand className="h-4 w-4" />
            </div>
          </button>

          {layout === 'dots' && images.length > 1 && (
            <>
              <button
                type="button"
                className="product-gallery__arrow product-gallery__arrow--left"
                onClick={(event) => { event.stopPropagation(); handlePrev(); }}
                aria-label="Image précédente"
              >
                <ChevronLeft className="h-5 w-5" />
              </button>
              <button
                type="button"
                className="product-gallery__arrow product-gallery__arrow--right"
                onClick={(event) => { event.stopPropagation(); handleNext(); }}
                aria-label="Image suivante"
              >
                <ChevronRight className="h-5 w-5" />
              </button>
              <DotsNav count={images.length} activeIndex={activeIndex} onSelect={setActiveIndex} />
            </>
          )}
        </div>

        {layout === 'thumbnails-bottom' && (
          <ThumbnailsRow
            images={images}
            activeIndex={activeIndex}
            productName={productName}
            onSelect={setActiveIndex}
          />
        )}
      </div>

      <AnimatePresence>
        {lightboxOpen && (
          <Lightbox
            images={images}
            activeIndex={activeIndex}
            productName={productName}
            onClose={() => setLightboxOpen(false)}
            onPrev={handlePrev}
            onNext={handleNext}
          />
        )}
      </AnimatePresence>
    </>
  );
}
