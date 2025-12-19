const Stats1 = () => {
  return (
    <section className="py-32">
      <div className="container">
        <h1 className="text-center text-4xl font-semibold lg:text-6xl">
          Platform Performance Insights
        </h1>
        <div className="grid gap-10 pt-9 md:grid-cols-3 lg:gap-0 lg:pt-20">
          <div className="text-center">
            <p className="text-muted-foreground text-sm font-medium">
              Reduce your time to hire by
            </p>
            <p className="pt-4 text-7xl font-semibold lg:pt-10">4x</p>
            <p className="text-muted-foreground text-2xl font-semibold">
              quicker
            </p>
          </div>
          <div className="text-center">
            <p className="text-muted-foreground text-sm font-medium">
              Clients have seen a decrease in
            </p>
            <p className="pt-4 text-7xl font-semibold lg:pt-10">50%</p>
            <p className="text-muted-foreground text-2xl font-semibold">
              in time to hire
            </p>
          </div>
          <div className="text-center">
            <p className="text-muted-foreground text-sm font-medium">
              The average number of hires per
            </p>
            <p className="pt-4 text-7xl font-semibold lg:pt-10">3</p>
            <p className="text-muted-foreground text-2xl font-semibold">
              months
            </p>
          </div>
        </div>
      </div>
    </section>
  );
};

export { Stats1 };
